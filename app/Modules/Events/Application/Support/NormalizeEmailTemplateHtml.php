<?php

namespace App\Modules\Events\Application\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Ensures images render as email-safe blocks (or side-by-side rows) so following text does not wrap beside them,
 * while preserving organizer-chosen widths/heights and side-by-side placement.
 */
final readonly class NormalizeEmailTemplateHtml
{
    public function execute(string $html): string
    {
        if ($html === '' || ! str_contains(strtolower($html), '<img')) {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<!DOCTYPE html><html><body><div id="email-root">'.$html.'</div></body></html>';
        $document->loadHTML($wrapped);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('email-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        /** @var list<DOMElement> $images */
        $images = [];
        foreach ($root->getElementsByTagName('img') as $img) {
            if ($img instanceof DOMElement) {
                $images[] = $img;
            }
        }

        foreach ($images as $img) {
            $this->normalizeImageElement($document, $img);
        }

        $this->hardenLayoutTables($root);
        $this->stripEditorArtifacts($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function normalizeImageElement(DOMDocument $document, DOMElement $img): void
    {
        if (! $img->parentNode instanceof DOMNode) {
            return;
        }

        $class = trim(preg_replace('/\b(is-selected|isSelectedEnd)\b/', '', $img->getAttribute('class')) ?? '');
        if (! str_contains($class, 'email-template-image')) {
            $class = trim($class.' email-template-image');
        }
        $img->setAttribute('class', $class);
        $img->removeAttribute('contenteditable');
        $img->setAttribute('draggable', 'false');

        $this->syncImageDimensions($img);

        if ($this->closestTableWithClass($img, 'email-image-row') !== null
            || $this->closestTableWithClass($img, 'email-image-block') !== null) {
            return;
        }

        $table = $this->createImageBlockTable($document, $img->cloneNode(true));
        $parent = $img->parentNode;

        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'p') {
            $hasMeaningfulSibling = $this->hasMeaningfulSibling($parent, $img);
            $grandParent = $parent->parentNode;

            if (! $hasMeaningfulSibling && $grandParent instanceof DOMNode) {
                $grandParent->replaceChild($table, $parent);

                return;
            }

            if ($grandParent instanceof DOMNode) {
                $this->splitParagraphAroundImage($parent, $img, $table);
            }

            return;
        }

        $parent->replaceChild($table, $img);
    }

    private function syncImageDimensions(DOMElement $img): void
    {
        $style = $img->getAttribute('style');
        $width = $this->readDimension($img->getAttribute('width'), $style, 'width');
        $height = $this->readDimension($img->getAttribute('height'), $style, 'height');

        $parts = [];
        foreach (preg_split('/\s*;\s*/', $style) ?: [] as $declaration) {
            if ($declaration === '') {
                continue;
            }
            if (preg_match('/^(width|height|max-width|display|border|border-style|margin)\s*:/i', $declaration) === 1) {
                continue;
            }
            $parts[] = $declaration;
        }

        $parts[] = 'display:block';
        $parts[] = 'border:0';
        $parts[] = 'margin:0';

        if ($width !== null) {
            $img->setAttribute('width', (string) $width);
            $parts[] = 'width:'.$width.'px';
            $parts[] = 'max-width:'.$width.'px';
        } else {
            $parts[] = 'max-width:100%';
            $parts[] = 'height:auto';
        }

        if ($height !== null) {
            $img->setAttribute('height', (string) $height);
            $parts[] = 'height:'.$height.'px';
        } elseif ($width !== null) {
            // Keep explicit width; allow natural height only when height was never set.
            $parts[] = 'height:auto';
            $img->removeAttribute('height');
        }

        $img->setAttribute('style', implode(';', $parts).';');

        $cell = $img->parentNode;
        if ($cell instanceof DOMElement && strtolower($cell->tagName) === 'td' && $width !== null) {
            $cell->setAttribute('width', (string) $width);
            $cellStyle = $cell->getAttribute('style');
            if (preg_match('/\bwidth\s*:/i', $cellStyle) === 1) {
                $cellStyle = (string) preg_replace('/\bwidth\s*:\s*[^;]+;?/i', 'width:'.$width.'px;', $cellStyle);
            } else {
                $cellStyle = trim($cellStyle);
                $cellStyle .= ($cellStyle === '' || str_ends_with($cellStyle, ';') ? '' : ';').'width:'.$width.'px;';
            }
            $cell->setAttribute('style', $cellStyle);
        }
    }

    private function readDimension(string $attributeValue, string $style, string $property): ?int
    {
        if (preg_match('/^(\d+(?:\.\d+)?)/', trim($attributeValue), $matches) === 1) {
            return max(1, (int) round((float) $matches[1]));
        }

        if (preg_match('/\b'.preg_quote($property, '/').'\s*:\s*(\d+(?:\.\d+)?)px\b/i', $style, $matches) === 1) {
            return max(1, (int) round((float) $matches[1]));
        }

        return null;
    }

    private function hardenLayoutTables(DOMElement $root): void
    {
        /** @var list<DOMElement> $tables */
        $tables = [];
        foreach ($root->getElementsByTagName('table') as $table) {
            if ($table instanceof DOMElement) {
                $tables[] = $table;
            }
        }

        foreach ($tables as $table) {
            $classes = preg_split('/\s+/', trim($table->getAttribute('class'))) ?: [];
            if (! in_array('email-image-row', $classes, true) && ! in_array('email-image-block', $classes, true)) {
                continue;
            }

            $table->setAttribute('role', 'presentation');
            $table->setAttribute('cellpadding', '0');
            $table->setAttribute('cellspacing', '0');
            $table->setAttribute('border', '0');

            if (in_array('email-image-row', $classes, true)) {
                $table->removeAttribute('width');
                $table->setAttribute('style', 'width:auto;max-width:100%;margin:12px 0;border-collapse:collapse;');
            } else {
                $table->setAttribute('width', '100%');
                $table->setAttribute('style', 'width:100%;margin:12px 0;border-collapse:collapse;');
            }

            foreach ($table->getElementsByTagName('td') as $td) {
                if (! $td instanceof DOMElement) {
                    continue;
                }
                $td->setAttribute('valign', 'top');
                $td->setAttribute('align', 'left');
                $padding = in_array('email-image-row', $classes, true) ? '0 6px' : '0';
                $width = $td->getAttribute('width');
                $style = 'padding:'.$padding.';vertical-align:top;';
                if ($width !== '' && is_numeric($width)) {
                    $style .= 'width:'.(int) $width.'px;';
                }
                $td->setAttribute('style', $style);
            }
        }
    }

    private function splitParagraphAroundImage(DOMElement $paragraph, DOMElement $img, DOMElement $table): void
    {
        $grandParent = $paragraph->parentNode;
        if (! $grandParent instanceof DOMNode) {
            return;
        }

        $before = $paragraph->cloneNode(false);
        $after = $paragraph->cloneNode(false);
        if (! $before instanceof DOMElement || ! $after instanceof DOMElement) {
            return;
        }

        $reachedImage = false;
        /** @var list<DOMNode> $children */
        $children = [];
        foreach ($paragraph->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->isSameNode($img)) {
                $reachedImage = true;
                continue;
            }
            if (! $reachedImage) {
                $before->appendChild($child);
            } else {
                $after->appendChild($child);
            }
        }

        $grandParent->insertBefore($before, $paragraph);
        $grandParent->insertBefore($table, $paragraph);
        if ($after->hasChildNodes()) {
            $grandParent->insertBefore($after, $paragraph);
        }
        $grandParent->removeChild($paragraph);
    }

    private function hasMeaningfulSibling(DOMElement $parent, DOMElement $img): bool
    {
        foreach ($parent->childNodes as $sibling) {
            if ($sibling->isSameNode($img)) {
                continue;
            }
            if ($sibling instanceof DOMElement && strtolower($sibling->tagName) === 'br') {
                continue;
            }
            if ($sibling->nodeType === XML_TEXT_NODE && trim((string) $sibling->nodeValue) === '') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function createImageBlockTable(DOMDocument $document, DOMNode $imgNode): DOMElement
    {
        $table = $document->createElement('table');
        $table->setAttribute('role', 'presentation');
        $table->setAttribute('width', '100%');
        $table->setAttribute('cellpadding', '0');
        $table->setAttribute('cellspacing', '0');
        $table->setAttribute('border', '0');
        $table->setAttribute('class', 'email-image-block');
        $table->setAttribute('style', 'width:100%;margin:12px 0;border-collapse:collapse;');

        $tr = $document->createElement('tr');
        $td = $document->createElement('td');
        $td->setAttribute('align', 'left');
        $td->setAttribute('valign', 'top');
        $td->setAttribute('style', 'padding:0;vertical-align:top;');
        $td->appendChild($imgNode);
        $tr->appendChild($td);
        $table->appendChild($tr);

        return $table;
    }

    private function closestTableWithClass(DOMElement $node, string $className): ?DOMElement
    {
        $current = $node->parentNode;
        while ($current instanceof DOMElement) {
            if (strtolower($current->tagName) === 'table') {
                $classes = preg_split('/\s+/', trim($current->getAttribute('class'))) ?: [];
                if (in_array($className, $classes, true)) {
                    return $current;
                }
            }
            $current = $current->parentNode;
        }

        return null;
    }

    private function stripEditorArtifacts(DOMElement $root): void
    {
        /** @var list<DOMElement> $elements */
        $elements = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            $element->removeAttribute('contenteditable');
            $class = $element->getAttribute('class');
            if ($class === '') {
                continue;
            }
            $cleaned = trim(preg_replace('/\b(is-selected|isSelectedEnd)\b/', '', $class) ?? $class);
            if ($cleaned === '') {
                $element->removeAttribute('class');
            } else {
                $element->setAttribute('class', $cleaned);
            }
        }
    }
}

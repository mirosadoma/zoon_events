type SectionElement = Record<string, unknown>

type SectionPreset = {
  id: string
  labelEn: string
  labelAr: string
  en: { title: string; subtitle: string; elements: SectionElement[] }
  ar: { title: string; subtitle: string; elements: SectionElement[] }
  options?: Record<string, unknown>
}

function el(id: string, patch: SectionElement): SectionElement {
  return { id, ...patch }
}

export const SECTION_PRESETS: SectionPreset[] = [
  {
    id: 'stats',
    labelEn: 'Stats row',
    labelAr: 'صف أرقام',
    en: {
      title: 'By the numbers',
      subtitle: 'What makes this event special',
      elements: [
        el('s1', { kind: 'card', col_span: 4, align: 'center', title: '500+', body: 'Attendees' }),
        el('s2', { kind: 'card', col_span: 4, align: 'center', title: '50+', body: 'Speakers' }),
        el('s3', { kind: 'card', col_span: 4, align: 'center', title: '20+', body: 'Sessions' }),
      ],
    },
    ar: {
      title: 'أرقامنا',
      subtitle: 'ما يميز هذا الحدث',
      elements: [
        el('s1', { kind: 'card', col_span: 4, align: 'center', title: '+500', body: 'حضور' }),
        el('s2', { kind: 'card', col_span: 4, align: 'center', title: '+50', body: 'متحدث' }),
        el('s3', { kind: 'card', col_span: 4, align: 'center', title: '+20', body: 'جلسة' }),
      ],
    },
    options: { layout_preset: '3', gap: 'md', align: 'center', padding: 'lg' },
  },
  {
    id: 'features',
    labelEn: 'Feature cards',
    labelAr: 'بطاقات مميزات',
    en: {
      title: 'Why attend',
      subtitle: 'Three reasons you will love it',
      elements: [
        el('f1', { kind: 'icon', col_span: 4, align: 'center', label: '⚡', title: 'Fast' }),
        el('f2', { kind: 'card', col_span: 4, title: 'Networking', body: 'Meet peers and leaders in your field.' }),
        el('f3', { kind: 'card', col_span: 4, title: 'Insights', body: 'Actionable talks from industry experts.' }),
      ],
    },
    ar: {
      title: 'لماذا تحضر',
      subtitle: 'ثلاثة أسباب ستعجبك',
      elements: [
        el('f1', { kind: 'icon', col_span: 4, align: 'center', label: '⚡' }),
        el('f2', { kind: 'card', col_span: 4, title: 'تواصل', body: 'التقِ مع زملائك وقادة مجالك.' }),
        el('f3', { kind: 'card', col_span: 4, title: 'رؤى', body: 'جلسات عملية من خبراء الصناعة.' }),
      ],
    },
    options: { layout_preset: '3', gap: 'md', padding: 'lg' },
  },
  {
    id: 'testimonials',
    labelEn: 'Testimonials',
    labelAr: 'آراء الحضور',
    en: {
      title: 'What people say',
      subtitle: '',
      elements: [
        el('t1', { kind: 'quote', col_span: 6, body: '“An unforgettable experience — I learned more in two days than months of reading.”' }),
        el('t2', { kind: 'quote', col_span: 6, body: '“Perfect organization and world-class speakers.”' }),
      ],
    },
    ar: {
      title: 'ماذا يقول الحضور',
      subtitle: '',
      elements: [
        el('t1', { kind: 'quote', col_span: 6, body: '«تجربة لا تُنسى — تعلمت في يومين أكثر من قراءة شهور.»' }),
        el('t2', { kind: 'quote', col_span: 6, body: '«تنظيم مثالي ومتحدثين على مستوى عالمي.»' }),
      ],
    },
    options: { layout_preset: '2', gap: 'md', padding: 'lg' },
  },
  {
    id: 'cta',
    labelEn: 'CTA banner',
    labelAr: 'دعوة للتسجيل',
    en: {
      title: 'Ready to join?',
      subtitle: 'Secure your seat today — limited spots available.',
      elements: [
        el('c1', { kind: 'button', col_span: 12, align: 'center', label: 'Register now', href: 'registration' }),
      ],
    },
    ar: {
      title: 'جاهز للانضمام؟',
      subtitle: 'احجز مقعدك اليوم — المقاعد محدودة.',
      elements: [
        el('c1', { kind: 'button', col_span: 12, align: 'center', label: 'سجّل الآن', href: 'registration' }),
      ],
    },
    options: { align: 'center', padding: 'xl', background_preset: 'brand' },
  },
  {
    id: 'two_col',
    labelEn: 'Text + image',
    labelAr: 'نص وصورة',
    en: {
      title: '',
      subtitle: '',
      elements: [
        el('tw1', { kind: 'heading', col_span: 6, title: 'Tell your story' }),
        el('tw2', { kind: 'text', col_span: 6, body: 'Use this flexible column layout to mix headings, text, images, and buttons.' }),
        el('tw3', { kind: 'image', col_span: 6, alt: '' }),
      ],
    },
    ar: {
      title: '',
      subtitle: '',
      elements: [
        el('tw1', { kind: 'heading', col_span: 6, title: 'احكِ قصتك' }),
        el('tw2', { kind: 'text', col_span: 6, body: 'استخدم هذا التخطيط المرن لدمج العناوين والنصوص والصور والأزرار.' }),
        el('tw3', { kind: 'image', col_span: 6, alt: '' }),
      ],
    },
    options: { layout_preset: '2', gap: 'md', padding: 'lg' },
  },
  {
    id: 'divider_section',
    labelEn: 'Divider + text',
    labelAr: 'فاصل ونص',
    en: {
      title: 'Section break',
      subtitle: '',
      elements: [
        el('d1', { kind: 'divider', col_span: 12 }),
        el('d2', { kind: 'text', col_span: 12, align: 'center', body: 'Continue reading below' }),
      ],
    },
    ar: {
      title: 'فاصل',
      subtitle: '',
      elements: [
        el('d1', { kind: 'divider', col_span: 12 }),
        el('d2', { kind: 'text', col_span: 12, align: 'center', body: 'تابع القراءة أدناه' }),
      ],
    },
    options: { padding: 'md' },
  },
]

export function presetElementsWithIds(elements: SectionElement[]): SectionElement[] {
  return elements.map((item, index) => ({
    ...item,
    id: `e_${Math.random().toString(36).slice(2, 8)}_${index}`,
  }))
}

<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'db='.config('database.connections.mysql.database').PHP_EOL;

try {
    $cols = Schema::getColumnListing('event_zones');
    echo 'event_zones cols include floor_type='.(in_array('floor_type', $cols, true) ? 'yes' : 'no').PHP_EOL;
    echo 'event_zones cols include fill_image_path='.(in_array('fill_image_path', $cols, true) ? 'yes' : 'no').PHP_EOL;
} catch (Throwable $ex) {
    echo 'event_zones error: '.$ex->getMessage().PHP_EOL;
}

try {
    echo 'event_paths='.(Schema::hasTable('event_paths') ? 'yes' : 'no').PHP_EOL;
} catch (Throwable $ex) {
    echo 'event_paths error: '.$ex->getMessage().PHP_EOL;
}

$venue = App\Modules\AdminConsole\Infrastructure\Persistence\Models\EventVenue::query()
    ->whereKey(15)
    ->with(['zones', 'paths'])
    ->first();

if (! $venue) {
    echo "venue 15 not found\n";
    exit(1);
}

echo 'zones='.$venue->zones->count().' paths='.$venue->paths->count().PHP_EOL;

foreach ($venue->zones as $zone) {
    try {
        $payload = App\Modules\Events\Application\Support\EventZonePresenter::toArray($zone);
        echo 'zone '.$zone->id.' ok floor='.json_encode($payload['floor_type'] ?? null).PHP_EOL;
    } catch (Throwable $ex) {
        echo 'zone '.$zone->id.' FAIL: '.$ex->getMessage().PHP_EOL;
    }
}

foreach ($venue->paths as $path) {
    try {
        App\Modules\Events\Application\Support\EventPathPresenter::toArray($path);
        echo 'path '.$path->id." ok\n";
    } catch (Throwable $ex) {
        echo 'path '.$path->id.' FAIL: '.$ex->getMessage().PHP_EOL;
    }
}

$map = App\Modules\Events\Infrastructure\Persistence\Models\EventVenueMap::query()
    ->where('venue_id', 15)
    ->first();

if ($map) {
    try {
        $arr = App\Modules\Events\Application\Support\EventVenueMapPresenter::toArray($map);
        echo 'map ok keys='.implode(',', array_keys($arr)).PHP_EOL;
    } catch (Throwable $ex) {
        echo 'map FAIL: '.$ex->getMessage().PHP_EOL;
    }
} else {
    echo "no map row\n";
}

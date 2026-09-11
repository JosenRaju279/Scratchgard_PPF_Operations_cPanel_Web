<?php
use App\Models\EvidenceItem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('scratchgard:status', function () {
    $this->info('Scratchgard installed: '.(file_exists(storage_path('app/installed.lock')) ? 'yes' : 'no'));
    $this->info('PHP: '.PHP_VERSION);
    $this->info('DB: '.config('database.default'));
    $this->info('Evidence disk: '.env('EVIDENCE_DISK',config('filesystems.default')));
})->purpose('Show Scratchgard deployment status');

Schedule::call(function(){
    EvidenceItem::whereNull('deleted_at')
        ->whereNull('protected_reason')
        ->whereNotNull('retention_until')
        ->where('retention_until','<',now())
        ->orderBy('id')->chunkById(100,function($items){
            foreach($items as $item){
                try{
                    Storage::disk($item->disk)->delete(array_filter([$item->path,$item->preview_path]));
                    $item->update(['deleted_at'=>now()]);
                }catch(\Throwable $e){
                    report($e);
                }
            }
        });
})->dailyAt('02:20')->name('scratchgard-evidence-retention')->withoutOverlapping();

<?php
namespace App\Services;

use App\Models\EvidenceItem;
use App\Models\WorkOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Plugins\PluginRuntime;

class EvidenceStorageService
{
    public function store(WorkOrder $work, User $user, UploadedFile $file, array $meta): EvidenceItem
    {
        $runtime=app(PluginRuntime::class);$meta=$runtime->applyFilters('evidence.meta',$meta,$work,$user,$file);$runtime->doAction('evidence.before_store',$work,$user,$file,$meta);
        $disk = (string)$runtime->applyFilters('evidence.disk',env('EVIDENCE_DISK', env('FILESYSTEM_DISK','local')),$work,$user,$meta);
        $date = now()->format('Y/m');
        $stage = strtolower($meta['stage']);
        $slot = preg_replace('/[^a-z0-9_-]/i','_', $meta['slot_code']);
        $name = (string) Str::uuid().'.'.$file->getClientOriginalExtension();
        $dir = "evidence/{$date}/{$work->work_order_number}/{$stage}/{$slot}";
        $path = Storage::disk($disk)->putFileAs($dir,$file,$name);

        $retentionDays=(int)$runtime->applyFilters('evidence.retention_days',(int)\App\Models\Setting::getValue('evidence_retention_days',30),$work,$user,$meta);
        $item=EvidenceItem::create([
            'work_order_id'=>$work->id,
            'user_id'=>$user->id,
            'stage'=>$meta['stage'],
            'vehicle_area'=>$meta['vehicle_area'] ?? null,
            'slot_code'=>$meta['slot_code'],
            'disk'=>$disk,
            'path'=>$path,
            'mime_type'=>$file->getMimeType(),
            'size_bytes'=>$file->getSize(),
            'sha256'=>hash_file('sha256',$file->getRealPath()),
            'latitude'=>$meta['latitude'] ?? null,
            'longitude'=>$meta['longitude'] ?? null,
            'gps_accuracy'=>$meta['gps_accuracy'] ?? null,
            'captured_at'=>now(),
            'retention_until'=>now()->addDays(max(1,$retentionDays)),
        ]);
        $runtime->doAction('evidence.stored',$item->fresh(),$work,$user,$meta);
        return $item;
    }

    public function temporaryUrl(EvidenceItem $item, int $minutes=15): string
    {
        if ($item->disk === 's3') {
            return Storage::disk('s3')->temporaryUrl($item->path, now()->addMinutes($minutes));
        }
        return route('evidence.view',$item);
    }
}

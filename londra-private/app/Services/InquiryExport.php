<?php
declare(strict_types=1);
namespace App\Services;
use App\Domain\CsvSafety;
use App\Models\Inquiry;
use Symfony\Component\HttpFoundation\StreamedResponse;
final class InquiryExport
{
    public function one(Inquiry $record): StreamedResponse
    {
        abort_unless(auth()->check()&&(int)auth()->user()->administrator_slot===1,403);
        return response()->streamDownload(function()use($record):void{
            $stream=fopen('php://output','wb');
            if($stream===false){throw new \RuntimeException('Unable to open export stream.');}
            fputcsv($stream,['Reference','Type','Name','Email','Phone','Language','Status','Submitted UTC','Submitted London'],',','"','');
            $row=[$record->reference,$record->type,$record->name,$record->email,$record->phone,$record->locale,$record->status->value,$record->created_at->copy()->utc()->toIso8601String(),$record->created_at->copy()->timezone('Europe/London')->toIso8601String()];
            fputcsv($stream,array_map(CsvSafety::cell(...),$row),',','"','');fclose($stream);
        },$record->reference.'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}

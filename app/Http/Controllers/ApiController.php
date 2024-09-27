<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Rtmp;
use App\Models\RtmpLive;
use App\Models\CheckRtmpLive;
use App\Models\RtmpRecording;
use App\Models\CheckCopyright;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stevebauman\Location\Facades\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{
    public function copyright_old($stream_key, Request $request) {

        $timezone = $this->timezone($request);
        date_default_timezone_set($timezone);

        /** File move */
        $directoryMove = public_path('live_stream/dataCheck');
        $patternMove = $stream_key.'*.m3u8';
        $filesMove = glob($directoryMove . '/' . $patternMove);
        foreach ($filesMove as $key => $value) {
            $explodeFileNameMove = explode('/', $value);
            $outputFiles = public_path('live_stream/data/'.end($explodeFileNameMove));
            unlink($outputFiles);
            copy($value, $outputFiles);
        }

        $created_by = Auth::user()->id ?? 0;
        $folderPath = public_path('live_stream/dataCheck');
        $pattern = $stream_key.'*.ts';
       
        $get_rtmp = Rtmp::where('stream_key', $stream_key)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return response()->json($return_data, 201);
        }

        $files = array_map('basename', glob($folderPath . '/' . $pattern, GLOB_BRACE));
        if (count($files) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files found.',
            ];
            return response()->json($return_data, 201);
        }

        $get_copyright = CheckCopyright::where(['rtmp_id' => $get_rtmp->id, 'created_by' => $created_by])->get();
        $data_base_files = [];
        if (count($get_copyright) > 0) {
            foreach ($get_copyright as $value) {
                $data_base_files[] = $value->file_name;
            }
        }

        $notMatchingElements = array_diff($files, $data_base_files);
        if (count($notMatchingElements) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files is processed.',
            ];
            return response()->json($return_data, 201);
        }

        $temple_directory = public_path('storage/copyright/'.$stream_key);
        if (!is_dir($temple_directory)) {
            mkdir($temple_directory);     
        }

        $fileName = reset($notMatchingElements);
        $explodeFileName = explode('.', $fileName);
        $inputFile = public_path('live_stream/dataCheck/'.$fileName);
        $outputFile = $temple_directory.'/'.$explodeFileName[0].'.wav';
        
        if ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_NAME'] == 'localhost') {
            $command = public_path('liberary/ffmpeg/bin/ffmpeg.exe');
        } else {
            $command = "ffmpeg";
        }
        $ffmpegCommand = "{$command} -i {$inputFile} -acodec pcm_s16le -ar 44100 {$outputFile} 2>&1";
        exec($ffmpegCommand, $output, $exitStatus);
        if ($exitStatus !== 0) {
            $return_data = [
                'status' => false,
                'command' => $ffmpegCommand,
                'message' => "FFmpeg command encountered an error. Exit status: $exitStatus",
            ];
            return response()->json($return_data, 201);
        }

        $copyrightData = copyrightAPI($outputFile);
        if($copyrightData['status'] == false) {
            return response()->json($copyrightData, 201);
        }
        if (!isset($copyrightData['data']) || empty($copyrightData['data'])) {
            $return_data = [
                'status' => false,
                'message' => "Something went wrong (copyright).",
            ];
            return response()->json($return_data, 201);
        }

        // copy($inputFile, $outputFile);
        // unlink($inputFile);
        $copyrightedText = "COPYRIGHTED";
        $outputFile = public_path('live_stream/data/'.$fileName);
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
        if(isset($copyrightData['data']['status']['code']) && $copyrightData['data']['status']['code'] == 0) {
            // $ffmpegCommand = "{$command} -i {$inputFile} -c copy -an {$outputFile} 2>&1";
            // C:/wamp64/www/live_stream/public/liberary/ffmpeg/bin/ffmpeg.exe -i C:/wamp64/www/live_stream/public/live_stream/dataCheck/stream-10.ts -c:v copy -c:a aac -strict experimental -af "volume=0.2" C:/wamp64/www/live_stream/public/live_stream/data/stream-10-output.ts 2>&1
            // ffmpeg -i input.ts -af "volume=0.5" -vf "drawtext=text='Your Text Here':fontsize=24:fontcolor=white:x=w-tw-10:y=h-th-10" -c:v copy -c:a aac -strict experimental output.ts
            $ffmpegCommand = "{$command} -i {$inputFile} -c:v copy -c:a aac -strict experimental -af \"volume=0.1\" {$outputFile} 2>&1";
            exec($ffmpegCommand, $output, $exitStatus);
            if ($exitStatus !== 0) {
                $return_data = [
                    'status' => false,
                    'command' => $ffmpegCommand,
                    'message' => "FFmpeg command encountered an error. Exit status: $exitStatus",
                ];
                return response()->json($return_data, 201);
            }
        } else {
            copy($inputFile, $outputFile);
        }

        $insert_check_copyright = [
            'rtmp_id' => $get_rtmp->id,
            'created_by' => $created_by,
            'file_name' => $fileName,
            'date_time' => date("Y-m-d G:i:s"),
            'api_output' => json_encode($copyrightData),
        ];
        $insert = CheckCopyright::create($insert_check_copyright);
        if (!$insert) {
            $return_data = [
                'status' => false,
                'message' => "Something went wrong (insert).",
            ];
            return response()->json($return_data, 201);
        }

        return response()->json($copyrightData['data'], 200);
    }

    public function copyright($stream_key, Request $request) {

        $timezone = $this->timezone($request);
        date_default_timezone_set($timezone);
        $created_by = Auth::user()->id ?? 0;

        $insert_check_copyright = [
            'rtmp_id' => 0,
            'created_by' => $created_by,
            'file_name' => $request->filename,
            'date_time' => date("Y-m-d G:i:s"),
            'api_output' => json_encode([]),
        ];
        logDatas('monitor_hls_file', $insert_check_copyright);
        $insert = CheckCopyright::create($insert_check_copyright);
        if (!$insert) {
            $return_data = [
                'status' => false,
                'message' => "Something went wrong (insert).",
            ];
            return response()->json($return_data, 201);
        }

        return response()->json(['status' => true], 200);
    }

    public function copyrightWorking($stream_key, Request $request) {

        $timezone = $this->timezone($request);
        date_default_timezone_set($timezone);

        $created_by = Auth::user()->id ?? 0;
        $folderPath = public_path('live_stream/data');
        $pattern = $stream_key.'*.ts';
       
        $get_rtmp = Rtmp::where('stream_key', $stream_key)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return response()->json($return_data, 201);
        }

        $files = array_map('basename', glob($folderPath . '/' . $pattern, GLOB_BRACE));
        if (count($files) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files found.',
            ];
            return response()->json($return_data, 201);
        }

        $get_copyright = CheckCopyright::where(['rtmp_id' => $get_rtmp->id, 'created_by' => $created_by])->get();
        $data_base_files = [];
        if (count($get_copyright) > 0) {
            foreach ($get_copyright as $value) {
                $data_base_files[] = $value->file_name;
            }
        }

        $notMatchingElements = array_diff($files, $data_base_files);
        if (count($notMatchingElements) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files is processed.',
            ];
            return response()->json($return_data, 201);
        }

        $temple_directory = public_path('storage/copyright/'.$stream_key);
        if (!is_dir($temple_directory)) {
            mkdir($temple_directory);     
        }

        $fileName = reset($notMatchingElements);
        $explodeFileName = explode('.', $fileName);
        $inputFile = public_path('live_stream/data/'.$fileName);
        $outputFile = $temple_directory.'/'.$explodeFileName[0].'.wav';
        
        if ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_NAME'] == 'localhost') {
            $command = public_path('liberary/ffmpeg/bin/ffmpeg.exe');
        } else {
            $command = "ffmpeg";
        }
        $ffmpegCommand = "{$command} -i {$inputFile} -acodec pcm_s16le -ar 44100 {$outputFile} 2>&1";
        exec($ffmpegCommand, $output, $exitStatus);
        if ($exitStatus !== 0) {
            $return_data = [
                'status' => false,
                'command' => $ffmpegCommand,
                'message' => "FFmpeg command encountered an error. Exit status: $exitStatus",
            ];
            return response()->json($return_data, 201);
        }

        $copyrightData = copyrightAPI($outputFile);
        if($copyrightData['status'] == false) {
            return response()->json($copyrightData, 201);
        }
        if (!isset($copyrightData['data']) || empty($copyrightData['data'])) {
            $return_data = [
                'status' => false,
                'message' => "Something went wrong (copyright).",
            ];
            return response()->json($return_data, 201);
        }

        $insert_check_copyright = [
            'rtmp_id' => $get_rtmp->id,
            'created_by' => $created_by,
            'file_name' => $fileName,
            'date_time' => date("Y-m-d G:i:s"),
            'api_output' => json_encode($copyrightData),
        ];
        $insert = CheckCopyright::create($insert_check_copyright);
        if (!$insert) {
            $return_data = [
                'status' => false,
                'message' => "Something went wrong (insert).",
            ];
            return response()->json($return_data, 201);
        }

        return response()->json($copyrightData['data'], 200);
    }

    public function verify_streamKey(Request $request) {

        $timezone = $this->timezone();
        date_default_timezone_set($timezone);
        $this->logs($request);

        if ($_SERVER['SERVER_ADDR'] != '127.0.0.1' && $_SERVER['SERVER_NAME'] != 'localhost') {

            $command = '/var/www/html/live_stream/public/live_stream/monitor_hls.sh > /dev/null 2>&1 &';
            exec($command, $output, $exitStatus);
            if ($exitStatus !== 0) {
                $return_data = [
                    'status' => false,
                    'command' => $command,
                    'message' => "FFmpeg command encountered an error. Exit status: $exitStatus",
                ];
                logDatas('monitor_hls', $return_data);
                return response()->json($return_data, 404);
            }
        }

        $ip = $request->ip();
        $streamKey = $request->name;
        if (!isset($streamKey) || empty($streamKey)) {
            $return_data = [
                'status' => false, 
                'message' => "Invalid streamKey!", 
            ];
            return response()->json($return_data, 404);
        }

        $get_rtmp = Rtmp::where('stream_key', $streamKey)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return response()->json($return_data, 404);
        }

        $insert_rtmp_live_data = [
            'rtmp_id' => $get_rtmp->id,
            'date_time' => date("Y-m-d G:i:s"),
            'ip_address' => $ip
        ];
        $insert = RtmpLive::create($insert_rtmp_live_data);

        $insert_check_rtmp_live_data = [
            'rtmp_id' => $get_rtmp->id,
            'created_by' => $get_rtmp->created_by,
        ];
        CheckRtmpLive::create($insert_check_rtmp_live_data);

        /* // START Delete copyright data
        $folderPath = public_path('storage/copyright/'.$streamKey);
        $deleteFolder = deleteFolder($folderPath);
        if (!isset($deleteFolder)) {
            $return_data = [
                'status' => false, 
                'message' => "Copyright file not deleted!", 
            ];
            return response()->json($return_data, 404);
        }

        $delete = CheckCopyright::where('rtmp_id', $get_rtmp->id)->delete();
        if (!isset($delete)) {
            $return_data = [
                'status' => false, 
                'message' => "CheckCopyright not delete!",
            ];
            return response()->json($return_data, 404);
        }
        // END Delete copyright data */

        /* // START Delete livestream data
        $directoryMove = public_path('live_stream/data');
        $patternMove = $streamKey.'*.ts';
        $filesMove = glob($directoryMove . '/' . $patternMove);
        foreach ($filesMove as $key => $value) {
            unlink($value);
        }
        // END Delete livestream data */

        $return_data = [
            'status' => true,
            'message' => 'Stream started successfully.',
            'result' => $insert,
        ];
        return response()->json($return_data, 200);

        // abort("403", "Invalid streamKey.");
        // header('HTTP/1.1 200 OK');
        // header('HTTP/1.1 401 Unauthorized');
    }

    public function verify_streamDone(Request $request) {

        // $this->testCase();die;

        $timezone = $this->timezone();
        date_default_timezone_set($timezone);
        $this->logs($request);

        $streamKey = $request->name;
        if (!isset($streamKey) || empty($streamKey)) {
            $return_data = [
                'status' => false, 
                'message' => "Invalid streamKey!", 
            ];
            return response()->json($return_data, 404);
        }

        $get_rtmp = Rtmp::where('stream_key', $streamKey)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return response()->json($return_data, 404);
        }

        $update_rtmp_live_data = [
            'status' => 0
        ];
        $update = RtmpLive::where('rtmp_id', $get_rtmp->id)->update($update_rtmp_live_data);
        if (!isset($update)) {
            $return_data = [
                'status' => false, 
                'message' => "RtmpLive not update!", 
            ];
            return response()->json($return_data, 404);
        }

        $delete = CheckRtmpLive::where('rtmp_id', $get_rtmp->id)->delete();
        if (!isset($delete)) {
            $return_data = [
                'status' => false, 
                'message' => "CheckRtmpLive not delete!", 
            ];
            return response()->json($return_data, 404);
        }

        /* // START Delete copyright data
        $folderPath = public_path('storage/copyright/'.$streamKey);
        $deleteFolder = deleteFolder($folderPath);
        if (!isset($deleteFolder)) {
            $return_data = [
                'status' => false, 
                'message' => "Copyright file not deleted!", 
            ];
            return response()->json($return_data, 404);
        }
        
        $delete = CheckCopyright::where('rtmp_id', $get_rtmp->id)->delete();
        if (!isset($delete)) {
            $return_data = [
                'status' => false, 
                'message' => "CheckCopyright not delete!",
            ];
            return response()->json($return_data, 404);
        }
        // END Delete copyright data */

        if ($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_NAME'] == 'localhost') {
            $recordingData = $this->recording($streamKey);
            $logType = "recording Response";
        } else {
            $recordingData = $this->recordingS3Bucket($streamKey);
            $logType = "recordingS3Bucket Response";
        }

        $logData = $recordingData;
        logDatas($logType, $logData);

        if (isset($recordingData['status']) && $recordingData['status'] == false) {
            return response()->json($recordingData, 404);
        }

        $return_data = [
            'status' => true,
            'message' => 'Successfully!',
        ];
        return response()->json($return_data, 200);

        // abort("403", "Invalid streamKey.");
        // header('HTTP/1.1 200 OK');
        // header('HTTP/1.1 401 Unauthorized');
    }

    public function timezone() {

        // $ip = $request->ip();
        $ip = $_SERVER['REMOTE_ADDR'];
        $get_country_data = Location::get($ip);
        if(empty($get_country_data->timezone)) {
            $timezone = "Asia/Kolkata";
        } else {
            $timezone = $get_country_data->timezone;
        }
        return $timezone;
    }

    public function logs(Request $request) {

        $timezone = $this->timezone();
        date_default_timezone_set($timezone);
        
        $streamCall = $_REQUEST['call'];
        $logFile = public_path('live_stream/liveStreamLog.log');
        
        if ($streamCall == "publish") {
            $logMessage = '[' . date('Y-m-d H:i:s') . '] Live stream Started ::: ' . json_encode($_REQUEST) . "\n\n";
        }
        else {
            $logMessage = '[' . date('Y-m-d H:i:s') . '] Live stream stopped ::: ' . json_encode($_REQUEST) . "\n\n";
        }
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        return;
    }

    public function recordingS3Bucket(string $streamKey) {

        sleep(1);
        $timezone = $this->timezone();
        date_default_timezone_set($timezone);

        if (!isset($streamKey) || empty($streamKey)) {
            $return_data = [
                'status' => false, 
                'message' => "Invalid streamKey!", 
            ];
            return $return_data;
        }

        $get_rtmp = Rtmp::where('stream_key', $streamKey)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return $return_data;
        }

        // $directory = public_path('live_stream/recording');
        $directory = '/mnt/streaming/recording';
        $pattern = $streamKey . '*.flv';
        // $files = scandir($directory);
        // $files = glob($directory . '/' . $pattern);
        $files = array_map('basename', glob($directory . '/' . $pattern, GLOB_BRACE));
        if (count($files) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files found in live-stream recording folder.',
            ];
            return $return_data;
        }
        
        $temple_directory = public_path('storage/recording/'.$streamKey.'');
        if (!is_dir($temple_directory)) {
            mkdir($temple_directory);     
        }

        // START ffmpeg convert flv to mp4 
        $fileName = $files[0];
        $explodeFileName = explode('.', $fileName);
        // $inputFile = public_path('live_stream/recording/'.$fileName);
        $inputFile = '/mnt/streaming/recording/'.$fileName;
        $outputFile = public_path('storage/recording/'.$streamKey.'/'.$explodeFileName[0].'.mp4');
        $command = "ffmpeg";

        $ffmpegCommand = "{$command} -i {$inputFile} -c:v libx264 -c:a aac -strict experimental {$outputFile}";
        exec($ffmpegCommand, $output, $exitStatus);

        $logType = "FFMPEG";
        $logData = [
            'type' => 'ffmpeg',
            'command' => $ffmpegCommand,
            'status' => $exitStatus,
        ];
        logDatas($logType, $logData);

        if ($exitStatus !== 0) {
            $return_data = [
                'status' => false,
                'message' => "FFmpeg command encountered an error. Exit status: $exitStatus",
            ];
            return $return_data;
        }
        // END ffmpeg convert flv to mp4

        try {
            $folderPath = 'storage/recording/'.$streamKey;
            if (!Storage::disk('s3')->exists($folderPath)) {
                // Folder doesn't exist, create it
                Storage::disk('s3')->makeDirectory($folderPath);
            }

            $file_name = $explodeFileName[0].'.mp4'; // File name
            $localFilePath = $outputFile; // Local file path
            $s3FolderPath = $folderPath.'/'.$file_name; // S3 folder path

            Storage::disk('s3')->put($s3FolderPath, file_get_contents($localFilePath));
            $s3FileUrl = Storage::disk('s3')->url($s3FolderPath);

            $insert_rtmp_recording_data = [
                'rtmp_id' => $get_rtmp->id,
                'recording_url' => $s3FolderPath
            ];
            $insert = RtmpRecording::create($insert_rtmp_recording_data);
            if (!isset($insert->id) || empty($insert->id)) {
                $return_data = [
                    'status' => false,
                    'message' => 'Insert failed in database RtmpRecording.',
                ];
                return $return_data;
            }

            // Optionally, delete the local file after moving it to S3
            unlink($inputFile);
            unlink($outputFile);
            $return_data = [
                'status' => true,
                'insertedID' => $insert->id,
                'count_file' => count($files),
            ];
            return $return_data;
            exit;
        }
        catch (Aws\S3\Exception\S3Exception $e) {
            $return_data = [
                'status' => false,
                'message' => $e->getMessage(),
            ];
            return $return_data;
            // throw $th; 
            // echo $api_error = $e->getMessage(); 
        } 
        catch (\Throwable $th) {
            $return_data = [
                'status' => false,
                'message' => $th->getMessage(),
            ];
            return $return_data;
            // throw $th;
            // echo $api_error = $th->getMessage(); 
        }
    }

    public function recording(string $streamKey) {

        // sleep(1);
        echo "gfdgfdgdf";die;
        $timezone = $this->timezone();
        date_default_timezone_set($timezone);
        $return_data = [];

        if (!isset($streamKey) || empty($streamKey)) {
            $return_data = [
                'status' => false, 
                'message' => "Invalid streamKey!", 
            ];
            return $return_data;
        }

        $get_rtmp = Rtmp::where('stream_key', $streamKey)->first();
        if (empty($get_rtmp)) {
            $return_data = [
                'status' => false, 
                'message' => "Stream not found!", 
            ];
            return $return_data;
        }

        $directory = public_path('live_stream/recording');
        $pattern = $streamKey . '*.flv';
        // $files = scandir($directory);
        // $files = glob($directory . '/' . $pattern);
        $files = array_map('basename', glob($directory . '/' . $pattern, GLOB_BRACE));
        if (count($files) < 1) {
            $return_data = [
                'status' => false,
                'message' => 'No files found in live-stream recording folder.',
            ];
            return $return_data;
        }

        $temple_directory = public_path('storage/recording/'.$streamKey.'');
        if (!is_dir($temple_directory)) {
            mkdir($temple_directory);     
        }

        foreach($files as $key => $value) {

            $sourcePath = $directory . '/' . $value;
            $destinationPath = $temple_directory . '/' . $value;

            if (rename($sourcePath, $destinationPath)) {

                $destinationPath = "storage/recording/".$streamKey."/".$value;
                $insert_rtmp_recording_data = [
                    'rtmp_id' => $get_rtmp->id,
                    'recording_url' => $destinationPath
                ];
                $insert = RtmpRecording::create($insert_rtmp_recording_data);
                if (!isset($insert->id) || empty($insert->id)) {
                    $return_data = [
                        'status' => false,
                        'message' => 'Insert failed in database RtmpRecording.',
                    ];
                    return $return_data;
                }

                $return_data[] = [
                    'status' => true,
                    'insertedID' => $insert->id,
                    'count_file' => count($files),
                ];
            } else {
                $return_data[] = [
                    'status' => false,
                    'message' => 'Failed to move the file.',
                ];
            }
        }
        return $return_data;
    }

    public function share_stream(Request $request, $type, $id) {

        $id = base64_decode($id);

        if (!empty($type) && $type == "record") {
            
            $records = RtmpRecording::with('rtmp')->where('status', 1)->where('id', $id)->first();
            if(empty($records->id)) {
                abort(404);
            }
            return view("backend.share", compact('records', 'type', 'id'));
        }
        if (!empty($type) && $type == "stream") {

            $records = CheckRtmpLive::with('rtmp')->where('rtmp_id', $id)->where('status', 1)->orderBy('id', 'DESC')->first();
            return view("backend.share", compact('records', 'type', 'id'));
        }
        abort(404);
    }

    public function testCase() {

        $folderPath = 'opt/data/hls';
        if (!Storage::disk('s3')->exists($folderPath)) {
            // Folder doesn't exist, create it
            // Storage::disk('s3')->makeDirectory($folderPath);
        }
        
        // echo Storage::disk('s3')->exists('storage/recording/yu5r05YH7Fsxx/yu5r05YH7Fsxx-1696863173-09-Oct-23-14_52_53.mp4');
        // echo Storage::disk('s3')->delete('storage/recording/yu5r05YH7Fsxx/yu5r05YH7Fsxx-1696863123-09-Oct-23-14_52_03.mp4');
        // echo Storage::disk('s3')->deleteDirectory('storage/recording/yu5r05YH7Fsxx');

        $files = Storage::disk('s3')->allFiles();
        foreach ($files as $file) {
            // Storage::disk('s3')->delete($file);
        }

        $folders = Storage::disk('s3')->directories();
        foreach ($folders as $folder) {
            // Storage::disk('s3')->deleteDirectory($folder);
        }

        echo "<pre>";
        print_r($files);
        print_r($folders);
        die;
    }
}

function deleteFolder($folderPath) {
    if (is_dir($folderPath)) {
        $files = scandir($folderPath);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    // Recursively delete sub-folders and files
                    deleteFolder($filePath);
                } else {
                    // Delete the file
                    unlink($filePath);
                }
            }
        }
        // Delete the folder
        rmdir($folderPath);
    }
    return true;
}

function copyrightAPI($filePath) {

    $http_method = "POST";
    $http_uri = "/v1/identify";
    $data_type = "audio";
    $signature_version = "1" ;
    $timestamp = time() ;

    $requrl = "https://".env('REQUEST_URL')."/v1/identify";
    $access_key =  env('ACCESS_KEY');
    $access_secret =  env('ACCESS_SECRET');

    $string_to_sign = $http_method . "\n" . $http_uri ."\n" . $access_key . "\n" . $data_type . "\n" . $signature_version . "\n" . $timestamp;
    $signature = hash_hmac("sha1", $string_to_sign, $access_secret, true);
    $signature = base64_encode($signature);

    // suported file formats: mp3,wav,wma,amr,ogg, ape,acc,spx,m4a,mp4,FLAC, etc 
    // File size: < 1M , You'de better cut large file to small file, within 15 seconds data size is better
    $argv[1] = $filePath;
    $file = $argv[1];
    $filesize = filesize($file);
    $cfile = new \CURLFile($file, "wav", basename($argv[1]));
    $postfields = array(
                "sample" => $cfile, 
                "sample_bytes"=>$filesize, 
                "access_key"=>$access_key, 
                "data_type"=>$data_type, 
                "signature"=>$signature, 
                "signature_version"=>$signature_version, 
                "timestamp"=>$timestamp
            );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    
    if ($response === false) {
        $return_data = [
            'status' => false,
            'message' => 'Curl error: '.curl_error($ch).PHP_EOL,
        ];
    } else {
        $response = json_decode($response, true);
        $return_data = [
            'status' => true,
            'message' => "API call successfully.",
            'data' => $response,
        ];
    }
    curl_close($ch);
    return $return_data;
    die;
}

function logDatas($type, $logData) {

    $logFile = public_path('live_stream/liveStreamLog.log');
    $logMessage = '[' . date('Y-m-d H:i:s') . '] '.$type.' ::: ' . json_encode($logData) . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    return true;
}

@extends("backend.layouts.app")

@section("css")
@endsection

@section("content")
<section class="main">
    <div class="container">
        <div class="row justify-content-center">

            <div class="col-12">
                <div class="video-wrapper">
                    <div class="cover">
                        <video id="video" controls class='video' width='600' muted autoplay>
                            <!-- <source
                                src="https://riverisland.scene7.com/is/content/RiverIsland/c20171205_Original_Penguin_AW17_Video"
                                type="video/mp4" />
                            <source
                                src="https://riverisland.scene7.com/is/content/RiverIsland/c20171205_Original_Penguin_AW17_Video_OGG" /> -->
                                <source src="{{ asset('assets') }}/img/sample_national_flag.mp4" type="video/mp4" />
                            <!-- <img src="fall-back image" alt=""> -->
                        </video>

                        <div class="overlayText">
                            <!-- Append Live OR Offline -->
                            <label class="m-1 blinking-text"></label>
                        </div>
                    </div>
                    <a class="video-play-btn" href="#"></a>
                </div>
                @if($type == "record")
                <!-- Append Temple Live Stream -->
                @if($_SERVER['SERVER_ADDR'] == '127.0.0.1' || $_SERVER['SERVER_NAME'] == 'localhost')
                    <input type="hidden" name="live_stream_recorded_url" id="live_stream_recorded_url" value="{{ asset('') }}{{ $records->recording_url }}">
                @else
                    <input type="hidden" name="live_stream_recorded_url" id="live_stream_recorded_url" value="{{ env('CLOUDFRONT_URL') }}/{{ $records->recording_url }}">
                @endif
            </div>
            @endif
            @if($type == "stream")
            <!-- Append Temple Live Stream -->
            @if(!empty($records))
            <input type="hidden" name="live_stream_url" id="live_stream_url" value="{{ $records->rtmp->live_url }}">
            <input type="hidden" name="live_stream_id" id="live_stream_id" value="{{ $records->rtmp->id }}">
            <input type="hidden" name="live_stream_key" id="live_stream_key" value="{{ $records->rtmp->stream_key }}">
            @endif
            @endif
        </div>

    </div>
    </div>
</section>
@endsection

@section("scripts")
<script>
$(document).ready(function() {

    var type = "{{ $type }}";

    if (type == "record") {
        record_stream();
    }

    if (type == "stream") {
        live_stream();
    }
});
</script>
@endsection
@extends('layouts/master')

@section('title')
    <title>Create Mod - Technic Solder</title>
@stop

@section('top')
<link href="{{asset('css/upload.css')}}" rel="stylesheet"/>
@stop

@section('content')
<div class="page-header">
<h1>Mod Library</h1>
</div>
<div class="panel panel-default">
	<div class="panel-heading">
	Add Mod
	</div>
	<div class="panel-body">
		@if ($errors->all())
            <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                {{ $error }}<br />
            @endforeach
            </div>
        @endif
		<form method="post" action="{{ URL::to('mod/create') }}">
		<div class="row">
			<div class="col-md-6">
				<div class="form-group">
                    <label for="pretty_name">Mod Name</label>
                    <input type="text" class="form-control" name="pretty_name" id="pretty_name">
                </div>
                <div class="form-group">
                    <label for="name">Mod Slug</label>
                    <input type="text" class="form-control" name="name" id="name">
                </div>
                <div class="form-group">
                    <label for="author">Author</label>
                    <input type="text" class="form-control" name="author" id="author">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="5"></textarea>
                </div>
                <div class="form-group">
                    <label for="link">Mod Website</label>
                    <input type="text" class="form-control" name="link" id="link">
                </div>
			</div>
			<div class="col-md-6">
				<p>
                    Good news! Solder now does file handling. Just drop your .jar (or .zip) files below and solder will handle the rest for you.<br/>
                    Sadly this has one limitation. If the Mods author did not provide a mcmod.info, you will still have to fill out the details of the mod.<br/>
                    The following structure will be kept:<br/>
                </p>
				<blockquote>/mods/<span class="modslug">[modslug]</span>/<br>
					/mods/<span class="modslug">[modslug]</span>/<span class="modslug">[modslug]</span>-[version].zip
				</blockquote>
                <hr/>
                <div id="uploader">
                    <!--<label for="file-upload" class="custom-file-upload">
                        <i class="fa fa-cloud-upload"></i>&nbsp;Click to upload mods
                    </label>-->
                    <input type="file" name="file" id="modupload" multiple>
                </div>
                <hr/>
                <div id="uploads"></div>
			</div>
		</div>
		{!! Form::submit('Add Mod', ['class' => 'btn btn-success']) !!}
		{!! Html::link('mod/list/', 'Go Back', ['class' => 'btn btn-primary']) !!}
		</form>
	</div>
</div>
@endsection
@section('bottom')
<script src="{{ asset('assets/SimpleUpload/js/simpleUpload.min.js') }}"></script>
<script type="text/javascript">
String.prototype.formatUnicorn = String.prototype.formatUnicorn || function () {
    "use strict";
    var str = this.toString();
    if (arguments.length) {
        var t = typeof arguments[0];
        var key;
        var args = ("string" === t || "number" === t) ?
            Array.prototype.slice.call(arguments)
            : arguments[0];

        for (key in args) {
            str = str.replace(new RegExp("\\{" + key + "\\}", "gi"), args[key]);
        }
    }

    return str;
};
</script>
<script>
	var modInfos = [];

	function addModInfo(modInfo) {
		//add provided info to the modinfos arr.
		modInfos[modInfo.modid] = modInfo;
	}

	function viewMod(modid) {
		//
	}

	function confirmModUpload(modid) {
		//
	}

	function editModInfo(modid) {
		//
	}

	function cancelModUpload(modid) {
		//
	}
</script>
<script type="text/javascript">
$("#name").slugify('#pretty_name');
$(".modslug").slugify("#pretty_name");
$("#name").keyup(function() {
	$(".modslug").html($(this).val());
});

$(document).ready(function() {
	/*$('.custom-file-upload').on('click', function(e){
		$('#modupload').click();
	});*/

	$('input[type=file]').change(function(){

		$(this).simpleUpload("/mod/upload", {

			allowedExts: ["jar","zip"],
			allowedTypes: [
				"application/java-archive",
				"application/x-java-archive",
				"application/x-jar",
				"application/zip",
				"application/octet-stream",
				"application/x-zip-compressed"
			],
			maxFileSize: 500000000, //500MB in bytes

			start: function(file){
				//upload started

				this.block = $('<div class="block"></div>');
				this.progressBar = $('<div class="progressBar"></div>');
				this.cancelButton = $('<div class="cancelButton">x</div>');

				/*
				* Since "this" differs depending on the function in which it is called,
				* we need to assign "this" to a local variable to be able to access
				* this.upload.cancel() inside another function call.
				*/

				var that = this;

				this.cancelButton.click(function(){
					that.upload.cancel();
					//now, the cancel callback will be called
				});

				this.block.append(this.progressBar).append(this.cancelButton);
				$('#uploads').append(this.block);

			},

			progress: function(progress){
				//received progress
				this.progressBar.width(progress + "%");
			},

			success: function(data){
				//upload successful

				var addTmpl = '<div class="block"><span>{prettyName}<code>{modid}</code></span>&nbsp;<small>v{modVersion}</small>&nbsp;<span>for Minecraft v</span><small>{mcVersion}</small>&nbsp;<div class="btn-group"><button class="btn btn-sm btn-info">View</button><button class="btn btn-sm btn-success">Confirm</button><button class="btn btn-sm btn-primary">Change</button><button class="btn btn-sm btn-danger">Cancel</button></div></div>';
				this.progressBar.remove();
				this.cancelButton.remove();

				if (data.success) {
					//now fill the block with the format of the uploaded file
					var format = data.format;
                    var modInfo = data.modInfo;
					var formatDiv = addTmpl.formatUnicorn(
						prettyName:modInfo.name,
						modid:modInfo.modid,
						modVersion:modInfo.version,
						mcVersion:modInfo.
						mcVersion
					);
					this.block.append(formatDiv);
					addModInfo(modInfo);
				} else {
					//our application returned an error
					var error = data.error.message;
					var errorDiv = $('<div class="error"></div>').text(error);
					this.block.append(errorDiv);
				}

			},

			error: function(error){
				//upload failed
				this.progressBar.remove();
				this.cancelButton.remove();
				var error = error.message;
				var errorDiv = $('<div class="error"></div>').text(error);
				this.block.append(errorDiv);
			},

			cancel: function(){
				//upload cancelled
				this.block.fadeOut(400, function(){
					$(this).remove();
				});
			}
		});
	});
});
</script>
@endsection
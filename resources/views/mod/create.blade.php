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
		<table id="uploads" class="table table-striped table-hover">
			<thead>
				<th>Mod</th>
				<th>Slug</th>
				<th>Mod-Version</th>
				<th>Minecraft-Version</th>
				<th>Actions</th>
			</thead>
		</table>
		{!! Html::link('mod/list/', 'Go Back', ['class' => 'btn btn-primary']) !!}
	</div>
</div>
<div id="modInfoModal" class="modal fade" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 id="modInfoModal_Header">ModName</h4>
			</div>
			<div class="modal-body">
				<form>
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
				</form>
			</div>
			<div class="modal-footer">
			</div>
		</div>
	</div>
</div>
@endsection

@section('bottom')
<script src="{{ asset('assets/SimpleUpload/js/simpleUpload.min.js') }}"></script>
<script>
	var modInfos = [];

	function addModInfo(infoPresent, modInfo = [], uploadedFile = '') {
		//add provided info to the modinfos arr.
		if (infoPresent) {
			modInfos[modInfo.modid] = modInfo;
		} else {
			//no modinfo was sent
			modInfos[uploadedFile] = [];
		}
	}

	function viewMod(modid) {
		//load mods infos into form:

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
	$('#uploads').dataTable({});

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

				this.row = $('<tr class="modUpload"></tr>');
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

				this.row.append(this.progressBar).append(this.cancelButton);
				$('#uploads').append(this.row);

			},

			progress: function(progress){
				//received progress
				this.progressBar.width(progress + "%");
			},

			success: function(data){
				//upload successful

				this.progressBar.remove();
				this.cancelButton.remove();

				if (data.success) {
					//now fill the block with the format of the uploaded file
					var format = data.format;
                    var modInfo = data.modInfo;
					var modInfo = data.modInfo;
					var formatFields = $('<td>'+modInfo.name+'</td><td>'+modInfo.modid+'</td><td>'+modInfo.version+'</td><td>'+modInfo.mcversion+'</td><td><div class="btn-group"><button class="btn btn-sm btn-info">View</button><button class="btn btn-sm btn-success">Confirm</button><button class="btn btn-sm btn-primary">Change</button><button class="btn btn-sm btn-danger">Cancel</button></div></td>');
					this.row.append(formatFields);
					if(data.modInfo == false) {
						addModInfo(false, [], data.uploadedFile);
					} else {
						addModInfo(true, modInfo);
					}
				} else {
					//our application returned an error
					var error = data.error.message;
					var errorDiv = $('<div class="error"></div>').text(error);
					this.row.append(errorDiv);
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
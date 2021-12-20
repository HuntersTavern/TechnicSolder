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
		<table class="table table-striped table-hover">
			<thead>
				<th>Mod</th>
				<th>Slug</th>
				<th>Mod-Version</th>
				<th>Minecraft-Version</th>
				<th>Actions</th>
			</thead>
			<tbody id="uploads">
			</tbody>
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
						<input type="text" class="form-control" name="name-new" id="name-new">
					</div>
					<div class="form-group">
						<label for="name">Mod Version</label>
						<input type="text" class="form-control" name="mod-version" id="mod-version">
					</div>
					<div class="form-group">
						<label for="name">for MC Version</label>
						<input type="text" class="form-control" name="mc-version" id="mc-version">
					</div>
					<div class="form-group">
						<label for="name">Add to Existing mod:</label>
						<select class="form-control" name="modselect" id="modselect" onchange="modselectChange()">
								<option value="0" selected>No, create new Mod</option>
							@foreach ($mods as $mod)
								<option value="{{$mod->name}}">{{$mod->pretty_name}}</option>
							@endforeach
						</select>
					</div>
					<div class="form-group">
						<label for="author">Author</label>
						<input type="text" class="form-control" name="author" id="author">
					</div>
					<div class="form-group">
						<label for="description">Description</label>
						<textarea class="form-control" name="description" id="description"  rows="5"></textarea>
					</div>
					<div class="form-group">
						<label for="link">Mod Website</label>
						<input type="text" class="form-control" name="link" id="link">
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button class="btn btn-success" onclick="updateMod()" id="setValuesButton" value="changeme">Set / Update Values</button>
				<button class="btn btn-success" onclick="confirmModUpload()" id="confirmUploadButton" value="changeme">Confirm Upload</button>
				<button class="btn btn-danger" onclick="cancelModUpload()" id="cancelUploadButton" value="changeme">Cancel Upload</button>
			</div>
		</div>
	</div>
</div>
@endsection

@section('bottom')
<script src="{{ asset('assets/SimpleUpload/js/simpleUpload.min.js') }}"></script>
<script>
	var modInfos = [];

	function addModInfo(modInfo = []) {
		//add provided info to the modinfos arr.
		modInfos[modInfo.modid] = modInfo;
	}

	function viewMod(modid) {
		//Load mod info:
		modInfo = modInfos[modid];
		//load mods infos into form:
		$('#pretty_name').val(modInfo.name);
		$('#name-new').val(modInfo.modid);
		$('#mod-version').val(modInfo.version);
		$('#mc-version').val(modInfo.mcversion);
		$('#author').val(modInfo.authorList.join());
		$('#description').val(modInfo.description);
		$('#link').val(modInfo.url);
		$('#setValuesButton').val(modInfo.modid);
		$('#confirmUploadButton').val(modInfo.modid);
		$('#cancelUploadButton').val(modInfo.modid);
		//Show modal:
		$('#modInfoModal').modal('show');
	}

	function updateMod() {
		//update the specified mod with values from modal
		modid = $('#setValuesButton').val();
		newModInfo = modInfos[modid]; //load old values.
		delete modInfos[modid]; //remove infos from array, will be set again at the end.
		modid = $('#name-new').val(); //update key
		newModInfo['modid'] = $('#name-new').val();
		newModInfo['name'] = $('#pretty_name').val();
		newModInfo['version'] = $('#mod-version').val();
		newModInfo['mcversion'] = $('#mc-version').val();
		newModInfo['url'] = $('#link').val();
		newModInfo['description'] = $('#description').val();
		newModInfo['authorList'] = $('#author').val().split(',');
		modInfos[modid] = newModInfo; //save new values.
		redrawTable();
		$('#modInfoModal').modal('hide');
	}

	$('#modselect').change(function() {
		//User changed the dropdown.
		console.log("dropdown change")
		if ($('#modselect').val() == 0) {
			console.log("enabling inputs")
			$('#pretty_name').prop("disabled", false);
			$('#name-new').prop("disabled", false);
			$('#author').prop("disabled", false);
			$('#description').prop("disabled", false);
			$('#link').prop("disabled", false);
		} else {
			//lock other inputs, user wants to add new version.
			console.log("disabling inputs")
			$('#pretty_name').prop("disabled", true);
			$('#name-new').prop("disabled", true);
			$('#author').prop("disabled", true);
			$('#description').prop("disabled", true);
			$('#link').prop("disabled", true);
		}
	});

	function redrawTable() {
		//clear table children
		$('#uploads').children('tr').remove()
		//Loop modInfos and readd them to table.
		for (i=0;i<Object.keys(modInfos).length;i++) {
			key = Object.keys(modInfos)[i];
			modInfo = modInfos[key];
			var formatFields = '<tr><td>'+modInfo.name+'</td><td>'+modInfo.modid+'</td><td>'+modInfo.version+'</td><td>'+modInfo.mcversion+'</td><td><div class="btn-group"><button class="btn btn-sm btn-info" onclick="viewMod(\''+modInfo.modid+'\')">View</button><!--<button class="btn btn-sm btn-success" onclick="confirmModUpload(\''+modInfo.modid+'\')">Confirm</button>--><button class="btn btn-sm btn-danger" onclick="cancelModUpload(\''+modInfo.modid+'\')">Cancel</button></div></td></tr>';
			$('#uploads').append(formatFields);
		}
	}

	function confirmModUpload(modid = 0) {
		if(modid == 0) {
			//new mod, use modid from input
			modid = $('#name-new').val();
		} else {
			//new version, use modid from #modselect
			modid = $('#modselect').val();
		}
		//Confirm the upload, so that the version gets moved to the correct folder.
		$.ajax({
			type: "POST",
			url: "{{ URL::to('mod/upload/confirm') }}",
			data: "filename="+modInfos[modid]['filename']+"&modid="+modid+"&modversion="+modInfos[modid]['version'],
			success: function (data) {
				if (data.status == "success") {
					console.log(data);
					$.jGrowl('Confirmed Upload.', { group: 'alert-success' });
				} else if (data.status == "warning") {
					$.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
				} else {
					$.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
				}
			},
			error: function (xhr, textStatus, errorThrown) {
				$.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
			}
		});
		//new mod or new version?
		if($('#modselect').val() == 0) {
			createMod();
		} else {
			addVersion();
		}
		
	}

	function createMod() {
		//new mod. use slug from #name-new
		modSlug = $('#name-new').val();
		//Map needed values
		createInfo = [];
		createInfo = modInfos[modSlug];
		$.ajax({
			type: "POST",
			url: "{{ URL::to('mod/create') }}",
			data: "name="+createInfo['modid']+"&pretty_name="+createInfo['name']+"&author="+createInfo['authorList']+"&description="+createInfo['description']+"&link="+createInfo['url'],
			success: function (data) {
				console.log(data);
				if (data.status == "success") {
					$.jGrowl('Created mod.', { group: 'alert-success' });
					modId = data.data.id;
					addVersion(modSlug,modId);
				} else if (data.status == "warning") {
					$.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
				} else {
					$.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
				}
			},
			error: function (xhr, textStatus, errorThrown) {
				$.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
			}
		});
	}

	function addVersion(modSlug = 0, modId = 0) {
		//new version for mod with id "modid"
		if (modSlug == 0) {
			modSlug = $('#name-new').val();
		}
		//Map needed values
		modVersion = modInfos[modSlug]['version'];
		$.ajax({
			type: "POST",
			url: "{{ URL::to('mod/add-version') }}",
			data: "mod-id="+modId+"&add-version="+modVersion,
			success: function (data) {
				console.log(data);
				if (data.status == "success") {
					$.jGrowl('Added version '+modVersion+" to "+modSlug, { group: 'alert-success' });
					delete modInfos[modSlug];
					$('#modInfoModal').modal('hide');
					redrawTable();
				} else if (data.status == "warning") {
					$.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
				} else {
					$.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
				}
			},
			error: function (xhr, textStatus, errorThrown) {
				$.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
			}
		});
	}

	function cancelModUpload(modid = 0) {
		if(modid == 0) {
			modid = $('#cancelUploadButton').val();
		}
		//remove from list, redraw table.
		delete modInfos[modid];
		$('#modInfoModal').modal('hide');
		redrawTable();
	}
</script>
<script type="text/javascript">
/*$("#name").slugify('#pretty_name');
$(".modslug").slugify("#pretty_name");
$("#name").keyup(function() {
	$(".modslug").html($(this).val());
});*/

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
					var filename = data.uploadedFile;
					if(modInfo == false || modInfo.length == 0) {
						//Set default values
						modInfo = [];
						modInfo["name"] = filename;
						modInfo["modid"] = filename;
						modInfo["authorList"] = ['none', 'defined'];
						modInfo["description"] = '';
						modInfo["url"] = '';
						modInfo["version"] = 0;
						modInfo["mcversion"] = 0;
					}
					modInfo["filename"] = filename;
					addModInfo(modInfo);
					redrawTable();
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
@extends('layouts/master')

@section('title')
    <title>Create Mod - Technic Solder</title>
@stop

@section('top')
<link href="{{asset('css/upload.css')}}" rel="stylesheet"/>
<link href="{{ asset('assets/Selectize/selectize.css') }}" rel="stylesheet">
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
						<label for="modselect">Add to Existing mod:</label>
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
<script src="{{ asset('assets/Selectize/selectize.min.js') }}"></script>
<script src="{{ asset('assets/SimpleUpload/js/simpleUpload.min.js') }}"></script>
<script src="{{ asset('js/modcreate.js') }}"></script>
<script>
	$(document).ready(function() {
		$('#modselect').selectize();
	})
</script>
@endsection
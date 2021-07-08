@extends('layouts/master')

@section('title')
    <title>Mod Library - Technic Solder</title>
@stop

@section('content')
<div class="page-header">
	<h1>Mod Library</h1>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		Upload Mods
	</div>
	<div class="panel-body">
		<div id="uploads"></div>
		<input type="file" name="file" multiple>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading">
		<div class="pull-right">
			<a href="{{ URL::to('mod/create') }}" class="btn btn-xs btn-success">Add Mod</a>
		</div>
		Mod List
	</div>
	<div class="panel-body">
		@if (Session::has('success'))
		<div class="alert alert-success">
			{{ Session::get('success') }}
		</div>
		@endif
		@if ($errors->all())
		<div class="alert alert-danger">
		@foreach ($errors->all() as $error)
			{{ $error }}<br />
		@endforeach
		</div>
		@endif
		<table class="table table-striped table-bordered table-hover" id="dataTables">
			<thead>
				<tr>
					<th>#</th>
					<th>Mod Name</th>
					<th>Author</th>
					<th>Website</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
			@foreach ($mods as $mod)
				<tr>
					<td>{!! Html::link('mod/view/'.$mod->id, $mod->id) !!}</td>
					<td>
						@if (!empty($mod->pretty_name))
							{!! Html::link('mod/view/'.$mod->id, $mod->pretty_name) !!} ({{ $mod->name }})
						@else
							{!! Html::link('mod/view/'.$mod->id, $mod->name) !!}
						@endif
						<br/>
						<b>Latest Version:</b> {{ !$mod->versions->isEmpty() ? $mod->versions->first()->version : "N/A" }}
					</td>
					<td>{{ !empty($mod->author) ? $mod->author : "N/A" }}</td>
					<td>{!! !empty($mod->link) ? Html::link($mod->link, $mod->link, ["target" => "_blank"]) : "N/A" !!}</td>
					<td>{!! Html::link('mod/view/'.$mod->id,'Manage', ["class" => "btn btn-xs btn-primary"]) !!}</td>
				</tr>
			@endforeach
		</table>
	</div>
</div>
@endsection

@section('bottom')
<script src="{{ asset('assets/SimpleUpload/js/simpleUpload.min.js') }}"></script>
<script type="text/javascript">
$(document).ready(function() {
	$('#dataTables').dataTable({
		"order": [[ 1, "asc" ]]
	});
	$('input[type=file]').change(function(){

		$(this).simpleUpload("/ajax/upload.php?getFormat=1", {

			allowedExts: ["jpg", "jpeg", "jpe", "jif", "jfif", "jfi", "png", "gif"],
			allowedTypes: ["image/pjpeg", "image/jpeg", "image/png", "image/x-png", "image/gif", "image/x-gif"],
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

				this.progressBar.remove();
				this.cancelButton.remove();

				if (data.success) {
					//now fill the block with the format of the uploaded file
					var format = data.format;
					var formatDiv = $('<div class="format"></div>').text(format);
					this.block.append(formatDiv);
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

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
		<form id="fileupload" action="{{URL::to('mod/upload')}}" method="POST" enctype="multipart/form-data">
			<div class="row fileupload-buttonbar">
				<div class="col-lg-7">
					<span class="btn btn-success fileinput-button">
						<i class="glyphicon glyphicon-plus"></i>
						<span>Add files...</span>
						<input type="file" name="files[]" multiple />
					</span>
					<button type="submit" class="btn btn-primary start">
						<i class="glyphicon glyphicon-upload"></i>
						<span>Start upload</span>
					</button>
					<button type="reset" class="btn btn-warning cancel">
						<i class="glyphicon glyphicon-ban-circle"></i>
						<span>Cancel upload</span>
					</button>
					<button type="button" class="btn btn-danger delete">
						<i class="glyphicon glyphicon-trash"></i>
						<span>Delete selected</span>
					</button>
					<input type="checkbox" class="toggle" />
					<span class="fileupload-process"></span>
				</div>
				<div class="col-lg-5 fileupload-progress fade">
					<div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" >
						<div class="progress-bar progress-bar-success" style="width: 0%;" ></div>
					</div>
					<div class="progress-extended">&nbsp;</div>
				</div>
			</div>
			<table role="presentation" class="table table-striped">
				<tbody class="files"></tbody>
			</table>
		</form>
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
<script type="text/javascript">
$(document).ready(function() {
	$('#dataTables').dataTable({
		"order": [[ 1, "asc" ]]
	});

});
</script>
@endsection

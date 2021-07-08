<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    @section('title')
      <title>Technic Solder {{ SOLDER_VERSION }}</title>
    @show
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link href="{{ asset('assets/Bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/font-awesome/css/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/jQuery/css/jquery.jgrowl.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/sbAdminsb-admin.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/dataTables/datatables.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/metisMenu/metisMenu.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/OpenSansfont.css') }}" rel="stylesheet">
    <link href="{{ asset('css/solder.css') }}" rel="stylesheet">
    @yield('top')
  </head>
  <body>
    <nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".sidebar-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{{ URL::to('dashboard') }}"><img src="{{ URL::asset('img/title.png') }}"> {{ SOLDER_VERSION }}</a>
        </div>
        <ul class="nav navbar-top-links navbar-left">
          @if (Cache::get('update'))
          <li>
              <a href="{{ URL::to('solder/update') }}" style="color:orangered;">
                 Update Available! <i class="fa fa-exclamation-triangle"></i>
              </a>
          </li>
          @endif
        </ul>
        <ul class="nav navbar-top-links navbar-right">
            <li><a href="https://docs.solder.io/" target="blank_">Help <i class="fa fa-question"></i></a></li>
            <li class="dropdown">
                <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                  {{ Auth::user()->username }} <i class="fa fa-user fa-fw"></i>  <i class="fa fa-caret-down"></i>
                </a>
                <ul class="dropdown-menu dropdown-user">
                    <li><a href="{{ URL::to('user/edit/'.Auth::user()->id) }}"><i class="fa fa-user fa-fw"></i> User Profile</a>
                    </li>
                    <li><a href="{{ URL::to('logout') }}"><i class="fa fa-sign-out fa-fw"></i> Logout</a>
                    </li>
                </ul>
                <!-- /.dropdown-user -->
            </li>
            <!-- /.dropdown -->
        </ul>
        <!-- /.navbar-top-links -->

    </nav>
    <!-- /.navbar-static-top -->


    <nav class="navbar-default navbar-static-side" role="navigation">
      <div class="sidebar-collapse">
          <ul class="nav side-menu" id="side-menu">
              <li>
                  <a href="{{ URL::to('dashboard') }}" {{ Request::is('dashboard') ? ' class="mm-active"' : '' }}><i class="fa fa-dashboard fa-fw"></i> Dashboard</a>
              </li>
              <li>
                  <a href="#"><i class="fa fa-folder fa-fw"></i> Modpacks<span class="fa arrow"></span></a>
                  <ul class="nav nav-second-level" {{ Request::is('modpack/*') ? ' class="mm-show"' : '' }}>

                       @foreach ($allModpacks as $modpack)
                        @if ($modpack->icon)
                          <li><a href="{{ URL::to('modpack/view/'.$modpack->id) }}"><img src="{{ URL::asset('resources/' . $modpack->slug . '/icon.png') }}" style="width: 16px; height: 16px;"> {{ $modpack->name }}{{ $modpack->hidden ? " (Hidden)" : "" }}</a></li>
                        @else
                          <li><a href="{{ URL::to('modpack/view/'.$modpack->id) }}"><img src="{{ URL::asset('resources/default/icon.png') }}" style="width: 16px; height: 16px;"> {{ $modpack->name }}{{ $modpack->hidden ? " (Hidden)" : "" }}</a></li>
                        @endif
                      @endforeach
                      <li><a href="{{ URL::to('modpack/list') }}">Modpack List</a></li>
                      <li><a href="{{ URL::to('modpack/create') }}">Add Modpack</a></li>
                  </ul>
                  <!-- /.nav-second-level -->
              </li>
              <li>
                  <a href="#"><i class="fa fa-book fa-fw"></i> Mod Library<span class="fa arrow"></span></a></a>
                  <ul class="nav nav-second-level" {{ Request::is('mod/*') ? ' class="mm-show"' : '' }}>
                       <li><a href="{{ URL::to('mod/list') }}">Mod List</a></li>
                       <li><a href="{{ URL::to('mod/create') }}">Add a Mod</a></li>
                  </ul>
              </li>
              <li>
                  <a href="#"><i class="fa fa-wrench fa-fw"></i> Configure Solder<span class="fa arrow"></span></a>
                  <ul class="nav nav-second-level" {{ Request::is('solder/*') ? ' class="mm-show"' : '' }}>
                      <li>
                          <a href="{{ URL::to('solder/configure') }}">Main Settings</a>
                      </li>
                      <li>
                          <a href="{{ URL::to('solder/update') }}">Update Checker</a>
                      </li>
                      <li>
                          <a href="{{ URL::to('user/list') }}">User Management</a>
                      </li>
                      <li>
                          <a href="{{ URL::to('client/list') }}">Client Management</a>
                      </li>
                      <li><a href="{{ URL::to('key/list') }}">API Key Management</a></li>
                  </ul>
                  <!-- /.nav-second-level -->
              </li>
          </ul>
          <!-- /#side-menu -->
      </div>
      <!-- /.sidebar-collapse -->
  </nav>
  <!-- /.navbar-static-side -->

  <div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
        @yield('content')
        </div>
    </div>
    <!-- /.row -->
</div>
<!-- /#page-wrapper -->
<script src="{{ asset('assets/jQuery/js/jquery-3.3.1.min.js') }}"></script>
<script src="{{ asset('assets/jQuery/js/jquery-migrate-3.0.0.min.js') }}"></script>
<script src="{{ asset('assets/jQuery/js/jquery.jgrowl.min.js') }}"></script>
<script src="{{ asset('assets/jQuery/js/jquery.slugify.js') }}"></script>
<script src="{{ asset('assets/Bootstrap/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('assets/metisMenu/metisMenu.min.js') }}"></script>
<script src="{{ asset('assets/sbAdmin/sb-admin.js') }}"></script>
<script src="{{ asset('assets/dataTables/datatables.min.js') }}"></script>
<script src="{{ asset('js/nav-float.js') }}"></script>
<!--Custom Scripts-->
<script type="text/javascript">
    (function($){
        $(function(){
            $.jGrowl.defaults.closerTemplate = '<div class="alert alert-info">Close All</div>';
        });
    })(jQuery);
</script>
@yield('bottom')
  </body>
</html>

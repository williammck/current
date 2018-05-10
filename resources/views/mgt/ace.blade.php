@extends('layout')
@section('title', 'ACE Team Management')
@section('content')
    <div class="container">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    ACE Team Management
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12">
                    {!! Form::open(array('url' => '/mgt/ace')) !!}
                        Add Controller: <input type="text" name="cid"> <button type="submit" class="btn btn-info">Add</button>
                    {!! Form::close() !!}
                    <table class="table table-striped">
                        @foreach ($roles as $ace)
                            <tr>
                                <td width="10%">{{$ace->cid}}</td>
                                <td width="80%">{{$ace->user()->first()->fullname()}}</td>
                                <td><button class="btn btn-danger" OnClick="aceDelete({{$ace->cid}})">Delete</button></td>
                            </tr>
                        @endforeach
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script type="text/javascript">
    function aceDelete(cid)
    {
        bootbox.confirm("Are you sure you want to delete " + cid + " from the ACE team?", function(result) {
            if (result === true) window.location="/mgt/ace/delete/"+cid;
        });
    }
</script>
@stop
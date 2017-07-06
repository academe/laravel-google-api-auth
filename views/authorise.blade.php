{{--
Simple POST form for the user to confirm they would like
to start a Google authorisation.
You would either override this view, or just bypass it completely, perhaps
with an and AJAX POST link or custom form elsewhere.
TODO: translations.
--}}
<html>
    <head>
        <title>Create Google Authorisation</title>
    </head>
    <body>
        <div>
            <form action="{{ $url }}" method="post">
                {{ csrf_field() }}

                @foreach($params as $param_name => $param_value)
                    <input type="hidden" name="{{ $param_name }}" value="{{ $param_value }}" />
                @endforeach

                <button type="submit">Create Google Authorisation</button>
            </form>
        </div>
    </body>
</html>

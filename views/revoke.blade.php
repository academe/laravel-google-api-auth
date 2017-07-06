{{--
Simple DELETE form for the user to confirm they would like
to revoke the authorisation.
You would either override this view, or just bypass it completely, perhaps
with an and AJAX DELETE link or custom form elsewhere.
TODO: translations.
--}}
<html>
    <head>
        <title>Revoke Google Authorisation</title>
    </head>
    <body>
        <div>
            <form action="{{ $url }}" method="post">
                <input name="_method" type="hidden" value="DELETE">

                {{ csrf_field() }}

                @foreach($params as $param_name => $param_value)
                    <input type="hidden" name="{{ $param_name }}" value="{{ $param_value }}" />
                @endforeach

                <button type="submit">Revoke Google Authorisation</button>
            </form>
        </div>
    </body>
</html>

<!DOCTYPE html>
<html lang="en" data-framework="emberjs">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projects 4 Me</title>

    {#  Load all the required CSS files #}
    {% for cssFile in cssFiles %}
    <link rel="stylesheet" href="{{ cssFile }}">
    {% endfor %}

    {#  Load all the required JS files #}
    {% for jsFile in jsFiles %}
    <script src="{{ jsFile }}"></script>
    {% endfor %}
    
    <!--script src="/vendors/ember-i18n-3.1.1/lib/i18n.js"></script-->
    <script src="/vendors/carhartl-jquery-cookie/jquery.cookie.js"></script>
</head>

</html>
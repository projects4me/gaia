<!DOCTYPE html>
<html lang="en"  data-framework="emberjs">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Projects 4 Me</title>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-theme.min.css">

    <script src="js/jquery.min.js"></script>
    <script src="js/handlebars.js"></script>
    <script src="js/ember.min.js"></script>
    <script src="js/ember-data.js"></script>
    <script src="js/ember-template-compiler.js"></script>
    <script src="js/ember.debug.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/app.js"></script>
    
</head>
    <body>
        {{ content() }}
    </body>
</html>


<script type="text/x-handlebars" data-template-name='application'>
    <div class="container">
    {{outlet}}
    </div>
</script>

<script type="text/x-handlebars" data-template-name='index'>
    <h1>Projects 4 Me</h1>
</script>

<script type="text/x-handlebars" data-template-name='login'>
      <form class="form-signin">
        <h2 class="form-signin-heading">Please sign in</h2>
        <label for="inputEmail" class="sr-only">Email address</label>
        <input type="text" id="inputEmail" class="form-control" placeholder="Email address" required autofocus>
        <label for="inputPassword" class="sr-only">Password</label>
        <input type="password" id="inputPassword" class="form-control" placeholder="Password" required>
        <div class="checkbox">
          <label>
            <input type="checkbox" value="remember-me"> Remember me
          </label>
        </div>
        <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
      </form>
</script>

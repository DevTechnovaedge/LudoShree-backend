<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - {{ env('APP_NAME') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-group { margin-bottom: 10px; }
        body{ height: 100vh;  }
        .bg{background: #6c63ff0f;}
        .f-sans-serif{ font-family: sans-serif;}
        .fw-900{ font-weight: 900; }

        .bg-theme {
                background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>);
                color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;
                }

        .bg-btn{ background:  linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>); color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;}
        .bg-btn:hover{ background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>); color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;}
        input.form-control:focus { outline: none; border-color: <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>; box-shadow: none; }

        @media(min-width:992px){
            img{
                height: 85vh;
            }
        }
    </style>
</head>

<body>

    <main class="h-100">

        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6 p-5">
                    <img src="{{ asset('admin-assets/dist/img/admin-bg.jpg') }}" class="w-100">
                </div>
                <div class="col-md-4 mx-auto">
                    <div class="text-center">

                        <h2 class="fw-900 f-sans-serif">{{ env('APP_NAME') }} - Admin Panel</h2>
                    </div>

                    <div class="card my-5 border-0 shadow">
                        <div class="card-header bg-theme">
                            <div class="text-center">

                                <h4>Login</h4>
                            </div>
                        </div>
                        <div class="card-body  p-4">
                            
                    <form method="post" action="{{ url('admin/login-process') }}">
                        @csrf

                        @if(session()->has('back_msg'))
                            <div class="alert alert-danger"> {{ session()->get('back_msg') }}</div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="email" class="form-control" name="username" placeholder="Enter Username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Enter Password" required>
                        </div>
                        <div class="text-center">

                            <button type="submit" class="btn bg-btn">Submit</button>
                        </div>
                    </form>
                </div>
                
                </div>
                    </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>

</html>
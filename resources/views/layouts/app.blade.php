@php
$download_url = url('download-apk');

$refer_code_segment = '';

if(request()->referCode):
$referCode = request()->referCode;
$download_url .= "?referCode=".request()->referCode;

$refer_code_segment .= "?referCode=".request()->referCode;
endif;

@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meri factory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Include Owl Carousel CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" />
    <style>
        * {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        p {
            font-size: 14px;
        }

        header {
            position: sticky;
            top: 0;
            width: 100%;
            z-index: 99999;
            transition: .4s all;
            backdrop-filter: blur(5px);
            background-color: #fdf7ecd8;
            border-bottom: 1px solid #cfcbc4;
        }

        body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-size: 16px;
            line-height: 1.7;
            font-family: 'Manrope', sans-serif;
            color: #000;
            background-color: #fdf7ec;
        }

        .banner {
            padding-top: 120px;
        }

        body {
            background: #fdf7ecd8;
        }

        .logo {
            max-height: 75px;
            border-radius: 9px;
        }

        .btn_block {
            position: relative;
        }

        .nav-link.dark_btn {
            padding: 0.5rem 2rem !important;
            color: #fff;
            background-color: #3f67f3;
            font-size: 16px;
            padding: 9px 40px;
            border-radius: 25px;
            position: relative;
            transform: translate3d(-3px, -4px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg);
            transform-style: preserve-3d;
            transition: .4s all;
            z-index: 2;
        }

        .nav-link.dark_btn:hover {
            transform: translate3d(0px, 0px, 0px) scale3d(1, 1, 1) rotateX(0deg) rotateY(0deg) rotateZ(0deg) skew(0deg, 0deg);
        }

        .banner_section .banner_text h1 span {
            color: #3f67f3;
        }

        .btn_bottom {
            z-index: 1;
            border: 1px solid #3f67f3;
            border-radius: 100px;
            position: absolute;
            top: -8px;
            left: 8px;
            right: -12px;
            transform: translate(-0.52em, 0.52em);
            width: 100%;
            height: 100%;
        }

        .banner_section .banner_text .type-wrap span {
            font-weight: 700;
            color: #3f67f3;
        }

        .banner_section .used_app ul {
            display: flex;
            align-items: center;
            margin: 0 10px 20px 0;
        }

        .banner_section {
            margin: 20px 0;
            position: relative;
        }

        .app_btn {
            display: flex;
            align-items: center;
            padding: 0;
            list-style-type: none;
            margin: 0;
            gap: 1rem;
        }

        .app_btn li a {
            display: flex;
            padding: 15px 35px;
            background-color: #000;
            border: none;
            position: relative;
            border-radius: 12px;
            transition: .4s all;
            /* margin-right: 1rem; */
        }

        a {
            text-decoration: none;
        }

        ul,
        li {
            padding: 0;
            list-style-type: none;
            margin: 0;
        }

        .footer_bottom .ft_inner .links li:not(:last-child)::after {
            content: "|";
            margin: 0 10px;
            color: #fff;
        }

        .review_section .coustomer_info .avtar img {
            width: 80px;
            aspect-ratio: 1 / 1;
            border-radius: 150px;
        }

        footer .contact_info {
            display: flex;
            margin-top: 10px;
        }

        footer .social_media {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }

        footer {
            background-color: var(--dark-black);
            padding-left: 15px !important;
            padding-right: 15px !important;
            background-image: url("{{ asset('assets/images/footer_bg.png') }}");
            background-repeat: no-repeat;
            background-position: 0 0;

            background-size: cover;

        }

        .footer_bottom {
            max-width: 1370px;
            margin: 0 auto;
            background-color: #111218;
            border-radius: 20px;
            margin-top: 40px;
        }

        .footer_bottom .ft_inner {
            display: flex;
            justify-content: center;
            padding: 20px 0;
        }

        .footer_bottom .ft_inner .links li a {
            color: #fff;
            transition: .4s all;
        }

        .footer_bottom .ft_inner .links {
            display: flex;
            justify-content: center;
        }

        footer .download_side {
            text-align: right;
            padding-top: 90px;
        }

        footer .download_side .app_btn {
            display: flex;
            align-items: center;
            justify-content: space-around;
            margin-top: 40px;
        }

        .avtar {
            text-align: -webkit-center;
        }

        .owl-carousel .item {
            height: auto;
            background: #4CAF50;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            justify-content: center;
            align-items: center;
        }

        footer .download_side .app_btn li a {
            display: block;
            margin: 3px;
            padding: 10px;
            background-color: #000;
            border: none;
            position: relative;
            border-radius: 12px;
            transition: .4s all;
        }

        footer .copy_text p {
            color: #fff;
            margin: 0;
        }

        footer .contact_info li a {
            color: #fff;
        }

        footer .contact_info li:not(:last-child)::after {
            content: "|";
            margin: 0 15px;
            color: #fff;
        }

        footer .social_media {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        footer .social_media li a {
            width: 35px;
            height: 35px;
            border: 1px solid #c2c2c2;
            border-radius: 100px;
            color: #fff;
            display: block;
            text-align: center;
            line-height: 35px;
            transition: .4s all;
        }

        .review_section .positive_inner .row .sticky-top {
            top: 30px;
        }

        .review_section .positive_inner .row .sticky-top {
            top: 30px;
        }

        .review_section {
            position: relative;
        }

        .review_section .review_side .review_block {
            background-color: #fff;
            border-radius: 20px;
            padding: 50px;
            margin-bottom: 30px;
        }

        .mobile-view {
            display: none;
        }

        @media screen and (max-width: 992px) {


            .banner_section .app_btn,
            .download_side {
                display: none;
            }

            .mobile-view .app_btn {
                justify-content: space-evenly;
            }

            .mobile-view {
                bottom: 0;
                position: sticky;
                width: 100%;
                z-index: 99999;
                transition: .4s all;
                backdrop-filter: blur(5px);
                background-color: #fdf7ecd8;
                border-bottom: 1px solid #cfcbc4;
                padding: 1rem;
                display: block;
            }


            .banner_section .app_btn li a {
                padding: 8px;
                margin: 0 5px;
            }

            .hero_img {
                margin: 1.5rem 0;
            }

            footer .contact_info {
                justify-content: center;
                flex-direction: column;
                align-items: center;
            }

            .app-screenshot {
                width: 100% !important;
                height: 70vh !important;
                margin: auto;
            }


            footer .social_media,
            footer .download_side .app_btn {
                justify-content: center;
                align-items: center;
            }

            footer {
                background-size: cover;
            }

            .downaload_section .app_btn {
                flex-direction: column;
                gap: 10px;
            }

            footer .download_side {
                text-align: center;
                padding-top: 20px;
            }

            ul.app_btn {
                display: flex;
            }


            .footer_bottom .ft_inner {
                display: flex;
                flex-direction: column;
                text-align: center;
            }

            .footer_bottom .copy_text {
                width: 100%;
            }

            .footer_bottom .links,
            .contact_info {
                width: 100%;
                display: flex;
                flex-direction: column;
                margin-bottom: 10px;
            }

            .footer_bottom {
                text-align: center;
            }

            .footer_bottom .ft_inner .links li:not(:last-child)::after {
                content: "";
            }

            footer .contact_info li:not(:last-child)::after {
                content: "";
            }


            .powered_by {
                text-align: center;
            }

            .blue_img {
                width: 100px;
            }
        }
    </style>

</head>

<body>
  

    <!-- Main Content -->
    <main class="container">
        @yield('content')
    </main>

    


    <!-- End Mobile View Sticky -->

    <!-- Add these at the end of your body tag -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Typed Js Cdn -->
    <script src="{{ asset('assets/js/typed.min.js') }}"></script>
    <!-- Include Owl Carousel JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
    <script>
        AOS.init();
        $("#typed").typed({
            strings: ["Super Fast &amp; Super Thrilling", "Instant Withdrawal", "Higher Winnings", "Up to 150% TDS Refund"],
            typeSpeed: 100,
            startDelay: 0,
            backSpeed: 60,
            backDelay: 2000,
            loop: true,
            cursorChar: "|",
            contentType: 'html'
        });
    </script>

    <script>
        $(document).ready(function() {
            $('.owl-carousel').owlCarousel({
                loop: true, // Infinite loop
                margin: 10, // Margin between items
                nav: false, // Show next/prev buttons
                autoplay: true, // Autoplay slides
                autoplayTimeout: 3000, // Autoplay interval
                responsive: {
                    0: {
                        items: 1 // Number of items on small screens
                    },
                    600: {
                        items: 2 // Number of items on medium screens
                    },
                    1000: {
                        items: 3 // Number of items on large screens
                    }
                }
            });

            $('.mobile_app_slide').owlCarousel({
                loop: true, // Infinite loop
                margin: 10, // Margin between items
                nav: false, // Show next/prev buttons
                autoplay: true, // Autoplay slides
                autoplayTimeout: 3000, // Autoplay interval
                responsive: {
                    0: {
                        items: 1 // Number of items on small screens
                    },
                    600: {
                        items: 2 // Number of items on medium screens
                    },
                    1000: {
                        items: 3 // Number of items on large screens
                    }
                }
            });
        });
    </script> -->

</body>

</html>
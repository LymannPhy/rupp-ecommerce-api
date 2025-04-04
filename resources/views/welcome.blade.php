<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel Bakong KHQR</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        {{-- Link Bootstrap --}}
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
       
        {{-- Bakong KHQR --}}
        <script src="https://github.com/davidhuotkeo/bakong-khqr/releases/download/bakong-khqr-1.0.6/khqr-1.0.6.min.js"></script>

        {{-- QR Code Reader --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    </head>
    <style>
                @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@500&family=Work+Sans:wght@800&display=swap');


        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;

        }

        body {
            padding: 15px;
            background-color: #ddc3c3;
        }

        .container {
            margin: 20px auto;
            max-width: 1000px;
            background-color: white;
            padding: 0;
        }


        .form-control {
            height: 25px;
            width: 150px;
            border: none;
            border-radius: 0;
            font-weight: 800;
            padding: 0 0 5px 0;
            background-color: transparent;
            box-shadow: none;
            outline: none;
            border-bottom: 2px solid #ccc;
            margin: 0;
            font-size: 14px;
        }

        .form-control:focus {
            box-shadow: none;
            border-bottom: 2px solid #ccc;
            background-color: transparent;
        }

        .form-control2 {
            font-size: 14px;
            height: 20px;
            width: 55px;
            border: none;
            border-radius: 0;
            font-weight: 800;
            padding: 0 0 5px 0;
            background-color: transparent;
            box-shadow: none;
            outline: none;
            border-bottom: 2px solid #ccc;
            margin: 0;
        }

        .form-control2:focus {
            box-shadow: none;
            border-bottom: 2px solid #ccc;
            background-color: transparent;
        }

        .form-control3 {
            font-size: 14px;
            height: 20px;
            width: 30px;
            border: none;
            border-radius: 0;
            font-weight: 800;
            padding: 0 0 5px 0;
            background-color: transparent;
            box-shadow: none;
            outline: none;
            border-bottom: 2px solid #ccc;
            margin: 0;
        }

        .form-control3:focus {
            box-shadow: none;
            border-bottom: 2px solid #ccc;
            background-color: transparent;
        }

        p {
            margin: 0;
        }

        img {
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        .text-muted {
            font-size: 10px;
        }

        .textmuted {
            color: #6c757d;
            font-size: 13px;
        }

        .fs-14 {
            font-size: 14px;
        }

        .btn.btn-primary {
            width: 100%;
            height: 55px;
            border-radius: 0;
            padding: 13px 0;
            background-color: black;
            border: none;
            font-weight: 600;
        }

        .btn.btn-primary:hover .fas {
            transform: translateX(10px);
            transition: transform 0.5s ease
        }


        .fw-900 {
            font-weight: 900;
        }

        ::placeholder {
            font-size: 12px;
        }

        .ps-30 {
            padding-left: 30px;
        }

        .h4 {
            font-family: 'Work Sans', sans-serif !important;
            font-weight: 800 !important;
        }

        .textmuted,
        h5,
        .text-muted {
            font-family: 'Poppins', sans-serif;
            }
    </style>
    <body>

        <!-- QR Code Modal Structure -->
        <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qrCodeModalLabel">Scan QR Code</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Canvas for QR code rendering -->
                        <canvas id="qrCodeCanvas"></canvas>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row m-0">
                <div class="col-lg-7 pb-5 pe-lg-5">
                    <div class="row">
                        <div class="col-12 p-5">
                            <img src="https://www.freepnglogos.com/uploads/honda-car-png/honda-car-upcoming-new-honda-cars-india-new-honda-3.png"
                                alt="">
                        </div>
                        <div class="row m-0 bg-light">
                            <div class="col-md-4 col-6 ps-30 pe-0 my-4">
                                <p class="text-muted">Mileage</p>
                                <p class="h5">25000<span class="ps-1">Km</span></p>
                            </div>
                            <div class="col-md-4 col-6  ps-30 my-4">
                                <p class="text-muted">Transmission</p>
                                <p class="h5 m-0">Manual</p>
                            </div>
                            <div class="col-md-4 col-6 ps-30 my-4">
                                <p class="text-muted">Drive unit</p>
                                <p class="h5 m-0">Front</p>
                            </div>
                            <div class="col-md-4 col-6 ps-30 my-4">
                                <p class="text-muted">Body</p>
                                <p class="h5 m-0">Coupe</p>
                            </div>
                            <div class="col-md-4 col-6 ps-30 my-4">
                                <p class="text-muted">Color</p>
                                <p class="h5 m-0">White</p>
                            </div>
                            <div class="col-md-4 col-6 ps-30 my-4">
                                <p class="text-muted">Daily UI</p>
                                <p class="h5 m-0">#002</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 p-0 ps-lg-4">
                    <div class="row m-0">
                        <div class="col-12 px-4">
                            <div class="d-flex align-items-end mt-4 mb-2">
                                <p class="h4 m-0"><span class="pe-1">ZAZ</span><span class="pe-1">966</span><span
                                        class="pe-1">B</span></p>
                                <P class="ps-3 textmuted">1L</P>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <p class="textmuted">Qty</p>
                                <p class="fs-14 fw-bold">1</p>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <p class="textmuted">Subtotal</p>
                                <p class="fs-14 fw-bold"><span class="fas fa-dollar-sign pe-1"></span>1,450</p>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <p class="textmuted">Shipping</p>
                                <p class="fs-14 fw-bold">Free</p>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <p class="textmuted">Promo code</p>
                                <p class="fs-14 fw-bold">-<span class="fas fa-dollar-sign px-1"></span>100</p>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <p class="textmuted fw-bold">Total</p>
                                <div class="d-flex align-text-top ">
                                    <span class="fas fa-dollar-sign mt-1 pe-1 fs-14 "></span><span class="h4">1,350</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 px-0">
                            <div class="row bg-light m-0">
                                <div class="col-12 px-4 my-4">
                                    <p class="fw-bold">Payment detail</p>
                                </div>
                                <div class="col-12 px-4">
                                    <div class="d-flex  mb-4">
                                        <span class="">
                                            <p class="text-muted">Card number</p>
                                            <input class="form-control" type="text" value="4485 6888 2359 1498"
                                                placeholder="1234 5678 9012 3456">
                                        </span>
                                        <div class=" w-100 d-flex flex-column align-items-end">
                                            <p class="text-muted">Expires</p>
                                            <input class="form-control2" type="text" value="01/2020" placeholder="MM/YYYY">
                                        </div>
                                    </div>
                                    <div class="d-flex mb-5">
                                        <span class="me-5">
                                            <p class="text-muted">Cardholder name</p>
                                            <input class="form-control" type="text" value="David J.Frias"
                                                placeholder="Name">
                                        </span>
                                        <div class="w-100 d-flex flex-column align-items-end">
                                            <p class="text-muted">CVC</p>
                                            <input class="form-control3" type="text" value="630" placeholder="XXX">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row m-0">
                                <div class="col-12  mb-4 p-0">
                                    <div class="btn btn-primary" id="checkout">Checkout<span class="fas fa-arrow-right ps-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>

    {{-- Script Bootstrap--}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    {{-- Script jquery  --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="{{asset('js/khqr.js')}}"></script>
</html>
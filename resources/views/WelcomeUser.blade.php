<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        body {
            background-color: #f6f6f6;
            font-family: sans-serif;
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }

        img {
            border: none;
            -ms-interpolation-mode: bicubic;
            max-width: 40%;
            max-height: 50%;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        p {
            text-align: justify;
            text-justify: inter-word;
            color: #707070;
        }

        .content {
            box-sizing: border-box;
            display: block;
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }

        .wrapper {
            margin-top: 50px;
        }

        .img-container {
            position: relative;
            text-align: center;
            color: white;
        }

        .centered {
            position: absolute;
            top: 30%;
            left: 30%;


        }

        .button {
            background-color: #1DB459;
            /* Green */
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
            margin: 4px 2px;
            transition-duration: 0.4s;
            cursor: pointer;
        }

        .btn {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        h1 {
            font-size: 20px;
            color: #1B212F;
            /* text-align: center; */
        }

        .card {
            background-color: #1DB459;
            color: white;
            text-align: center;
            display: flex;
            justify-content: center;
            margin: 6px;
            height: 170px;
            border-radius: 5px;
        }

        .footer {
            clear: both;
            margin-top: 30px;
            text-align: center;
            width: 100%;
        }

        .footer p,
        .footer a {
            color: #999999;
            font-size: 14px;
            text-align: center;
        }

        /* -------------------------------------
          RESPONSIVE AND MOBILE FRIENDLY STYLES
      ------------------------------------- */
        @media only screen and (max-width: 620px) {
            h1 {
                font-size: 16px;

            }

            .content {
                margin-left: 10px;
                margin-right: 10px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="content">
            <!-- <img src="www.afriproedu.com/images/logo.svg" alt="www.afriproedu.com/images/logo192.png"/> -->
            <!-- <div class="card">Design</div> -->
            <div class="img-container">

                <img src="https://www.mygupta.co/image.png" alt="https://afriproedu.com/logo192.png" class="img" />
                <!-- <div class="centered">Welcome to AfriProEdu:<br/> Your Gateway to a Bright Future in Finland!</div> -->
            </div>

            <h1>Dear {{$details['custname']}},</h1>


            <p> Welcome to Gupta, </p>
            <p> Gupta boosts your business by letting you create multiple shops with direct WhatsApp links for each product, making it super easy for customers to connect and buy.</p>

            <!-- <p>My name is Treasure and I'll be your personal study support counsellor to help guide you on questions that you may have during your study abroad journey or if you need any help while using the platform. I am excited that you have taken the first step in your study dream, and I can't wait to commence this journey with you.</p> -->
            <!-- <p>Kindly follow the steps below to submit an application.</p> -->

            <p>
                <span style="font-weight: 500;">Here's a brief overview of what Gupta has to offer: </span><br /><br />
                <span>- <b>Custom WhatsApp Links:</b> Easily create customized links to connect directly with customers on whatsapp.engagement.</span><br /><br />
                <span>- <b>Redirect Links: </b> Create short, custom URLs that redirect to any website, track link clicks, and generate QR codes for easy access.</span><br /><br />
                <span>- <b>Multilinks Management:</b>Create a mini webpage with your customized WhatsApp links, brand logos, and social media links to make it easier for your customers to connect with you.</span><br /><br />
                <span>- <b>Mini Store:</b> Create custom website URLs for your shop, add your products, and make it easy for customers to browse and shop.</span><br /><br />
                <span>- <b>Pay with Gupta:</b> Easily collect payments with Gupta payment links on your product pages and track customer payments through a mini wallet, where you can also make withdrawals.</span><br /><br />
            </p>

            <p>Thank you for choosing Gupta! We're excited to be your partner in revolutionizing your business.
                <br />
                Sincerely,<br />
                The Gupta Team
            </p>

        </div>
    </div>
    <!-- START FOOTER -->
    <div class="footer">
        <p>Sent by Gupta © 2024. All Rights Reserved.</p>
    </div>
    <!-- END FOOTER -->


</body>

</html>
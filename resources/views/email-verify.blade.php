<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<style>
    body {
        background-color: #ffffff;
        font-family: sans-serif;
        -webkit-font-smoothing: antialiased;
        font-size: 14px;
        /* line-height: 1.4; */
        margin: 0;
        padding: 0;
        -ms-text-size-adjust: 100%;
        -webkit-text-size-adjust: 100%;
    }

    .wrapper {
        background-color: #F2F5F8;
        margin: auto;
        margin-top: 50px;
        margin-bottom: 40px;
        width: 640px;
        padding-top: 15px;
        padding-bottom: 15px;
        padding-left: 32px;
        padding-right: 32px;

    }

    .content {
        background-color: white;
        width: 576px;
        margin: auto;
        margin-top: 20px;
        padding: 32px;
    }

    .header h3 {
        font-size: 16px;
        color: #333333;
    }

    .text-content {
        margin-top: 20px;
    }

    .text-content p {
        font-size: 14px;
        color: #333333;
    }

    .button {
        border: none;
        background-color: #0071BC;
        color: #ffffff;
        padding: 12px 22px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        border-radius: 20px;
    }

    .footer {
        padding: 32px;
    }

    .footer p {
        font-size: 14px;
        color: #333333;
    }

    .footer-end {
        display: flex;
        justify-content: space-between;

    }

    .footer-end h3 {
        color: #6A7C94;
        font-style: italic;

    }

    .footer-social img {
        padding-left: 10px;
        margin-top: 8px;
    }

    .logo img {
        text-align: center;
        position: relative;
       
    }
</style>

<body>
    <div class="wrapper">
        <div class="logo">
            <img src="https://www.mygupta.co/gupta.jpeg" />
        </div>
        <div class="content">

            <div class="header">
                <h3>Welcome to Gupta!</h3>
                <h3> Dear {{$details['custname']}},</h3>
                <h3>Welcome to Gupta! We're thrilled to have you on board.
                </h3>

                <p></p>
            </div>
            <div class="text-content">
                <!-- <p>To enhance your experience and streamline communication, we've integrated a convenient WhatsApp link
                    feature. Simply click the link below to join our dedicated WhatsApp group and stay connected with
                    the Gupta community.</p> -->
                <p>Kindly confirm yur email to proceed.</p>
                <p>Best regards,</p>
            </div>
            <div class="btn">
                <a href="https://www.mygupta.co/email-verify/{{$details['email']}}">
                    <button class="button">Confirm Email</button>
                </a>
            </div>
        </div>



        <div class="footer">
        <p>Sent by Gupta © 2024. All Rights Reserved.</p>
    </div>
        <div class="footer-end">
            <div>
                <h3>Gupta</h3>
            </div>
            <div class="footer-social">
                <img src="https://www.mygupta.co/twitter.jpeg" />
                <img src="https://www.mygupta.co/facebook.jpeg" />
                <img src="https://www.mygupta.co/linkedin.jpeg" />
            </div>
        </div>
    </div>
</body>

</html>
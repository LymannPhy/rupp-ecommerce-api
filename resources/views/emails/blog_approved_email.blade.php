<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Blog Post Has Been Published</title>
    <style>
        /* Base styles for email clients */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            color: #333333;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .email-header {
            background-color: #0088e0;
            padding: 20px;
            text-align: center;
        }
        
        .logo {
            width: 120px;
            height: auto;
        }
        
        .email-body {
            padding: 30px;
            line-height: 1.6;
        }
        
        h1 {
            color: #0088e0;
            margin-top: 0;
            font-size: 24px;
        }
        
        .button {
            display: inline-block;
            background-color: #66cc33;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .blog-details {
            background-color: #f9f9f9;
            border-left: 4px solid #66cc33;
            padding: 15px;
            margin: 20px 0;
        }
        
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            
            .email-body, .email-header {
                padding: 15px !important;
            }
            
            h1 {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/logo-fEpIZWMy5cCQG4bPI0jpGLf9NGGsKJ.png" alt="CAM-O2 Logo" class="logo">
        </div>
        
        <div class="email-body">
            <h1>Congratulations! Your Blog Post Has Been Published</h1>
            
            <p>Hello {{ $user_name }},</p>
            
            <p>We're excited to inform you that your blog post has been reviewed and approved by our admin team. Your content is now live on our website!</p>
            
            <div class="blog-details">
                <p><strong>Blog Title:</strong> {{ $blog_title }}</p>
                <p><strong>Publication Date:</strong> {{ $publication_date }}</p>
                <p><strong>Tags:</strong> {{ $blog_tags }}</p>
            </div>
            
            <p>Your contribution is valuable to our community, and we appreciate the time and effort you've put into creating this content.</p>
            
            <p>You can view your published blog post by clicking the button below:</p>
            
            <div style="text-align: center;">
                <a href="{{ $blog_url }}" class="button">View Your Published Blog</a>
            </div>
            
            <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
            
            <p>Thank you for being a part of our community!</p>
            
            <p>Best regards,<br>
            The CAM-O2 Team</p>
        </div>
    </div>
</body>
</html>
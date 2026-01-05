<?php

namespace App\Http\Traits;

trait HtmlSuccessPageTrait
{
    /**
     * Generate simple success HTML page.
     * Parameter $message dibuat fleksibel.
     */
    public function successHtmlPage(string $message)
    {
        return "
        <html>
            <head>
                <title>Success</title>
                <meta name='password-manager' content='disable'>
                <meta name='autofill' content='false'>
                <meta name='google' content='notranslate'>
                <style>
                    body {
                        font-family: Arial, sans-serif; 
                        background: #f5f5f5; 
                        padding: 40px; 
                        text-align: center;
                    }
                    .box {
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        display: inline-block;
                    }
                    button {
                        margin-top: 20px;
                        padding: 10px 20px;
                        border: none;
                        background: #3498db;
                        color: white;
                        border-radius: 5px;
                        font-size: 14px;
                        cursor: pointer;
                    }
                    button:hover {
                        background: #2980b9;
                    }
                </style>
            </head>
            <body>
                <div class='box'>
                    <h2>Sukses!</h2>
                    <p>{$message}</p>
                </div>
            </body>
        </html>";
    }
}

<?php
function getBotReply($msg)
{
    $msg = strtolower(trim($msg));


    $keywords = [
        "hi" => "Hello! 👋 Welcome to ToyWorld! How can I assist you today?",
        "hello" => "Hi there! Looking for toys? 🎁",
        "cars" => "We have remote cars, monster trucks, and mini racers! 🚗💨",
        "dolls" => "We offer Barbie dolls, plush dolls, and baby dolls! 🧸",
        "lego" => "LEGO sets range from RM49 to RM299 depending on the set.",
        "price" => "Our products start at RM10! What toy are you interested in?",
        "delivery" => "Yes! We deliver anywhere within Malaysia 🇲🇾",
        "hours" => "We're open daily from 9AM to 9PM! ⏰"
    ];


    foreach ($keywords as $key => $response) {
        if (strpos($msg, $key) !== false) {
            return $response;
        }
    }


    return "Hmm... I'm not sure about that 🤔 Try asking about *cars, dolls, LEGO, prices,* or *delivery*!";
}


if (isset($_POST["message"])) {
    echo getBotReply($_POST["message"]);
}

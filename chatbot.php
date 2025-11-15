<?php
function getBotReply($msg)
{
    $msg = strtolower(trim($msg));


    $keywords = [
        "hi" => "Hello! 👋 How can I help you today?",
        "hello" => "Hi there! How can I assist you?",
        "hey" => "Hey! Need help finding a toy?",
        "good morning" => "Good morning! 🌞 Looking for toys?",
        "good afternoon" => "Good afternoon! How can I help?",
        "good evening" => "Good evening! ✨ How may I assist?",


        // Product Categories
        "cars" => "We have remote cars, monster trucks, pull-back cars, and mini racers! 🚗💨",
        "rc car" => "Our RC cars range from RM49 to RM199 depending on model.",
        "dolls" => "We have Barbie dolls, plush dolls, baby dolls, and talking dolls! 🧸",
        "lego" => "We sell LEGO Classic, Technic, City, and more!",
        "plush" => "We have teddy bears, animal plushies, and jumbo plush toys! 🐻",
        "puzzle" => "Puzzles available from 20–500 pieces! 🧩",
        "board game" => "We have Monopoly, Ludo, Chess, and more board games!",
        "education" => "We offer STEM kits, building blocks, and learning toys for kids!",


        // Age-Based Suggestions
        "1 year" => "For 1-year-olds, we suggest soft toys, rattles, and sensory toys!",
        "2 years" => "For 2-year-olds: stacking toys, building blocks, and push toys!",
        "3 years" => "For 3-year-olds: puzzles, small cars, mini LEGO, and pretend-play sets!",
        "4 years" => "For 4-year-olds: action figures, basic board games, mini robots!",
        "5 years" => "For 5-year-olds: LEGO City, RC cars, craft kits!",


        // Store Information
        "price" => "Our products start from RM10! What toy are you interested in?",
        "delivery" => "Yes! We deliver anywhere in Malaysia 🇲🇾",
        "shipping" => "Shipping takes 1–3 days depending on location.",
        "payment" => "We accept online banking, debit/credit cards, and e-wallets.",
        "hours" => "We are open daily from 9AM to 9PM! ⏰",
        "location" => "We are located at ToyWorld Mall, Level 2! 🏬",


        // Customer Support
        "refund" => "Refunds are accepted within 7 days with receipt.",
        "return" => "You can return defective products for free.",
        "warranty" => "Electronic toys come with 30-day warranty.",
        "help" => "Sure! Tell me what you're looking for 😄",


        // Small Talk
        "thank" => "You're welcome! 💚",
        "thanks" => "Glad to help! 😊",
        "who are you" => "I'm the ToyWorld Assistant! I help customers find toys 🎁",
        "bye" => "Goodbye! Have a wonderful day 😄",
        "goodbye" => "See you again! 👋"
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

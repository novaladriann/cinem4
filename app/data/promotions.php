<?php
// data/promotions.php

$PROMOS = [
  [
    "id" => "bogo",
    "title" => "BOGOF ALERT: Drama Haru Buat Kamu!",
    "category" => "Movie",         // Movie | Event | Partner | Member
    "valid" => "Valid until 02/03/2026 - 05/03/2026",
    "img" => "assets/img/promo-bogo.png",
    "featured" => true,            // tampil di Home (section promos)
    "hero" => true,                // boleh masuk hero home
    "cta_link" => "promotions.php#bogo",
  ],
  [
    "id" => "cashback50",
    "title" => "CASHBACK 50%: Nonton Hemat, Saldo Balik Kilat!",
    "category" => "Partner",
    "valid" => "Valid until 27/02/2026 - 30/04/2026",
    "img" => "assets/img/promo-cashback.png",
    "featured" => true,
    "hero" => true,
    "cta_link" => "promotions.php#cashback50",
  ],
  [
    "id" => "weekend",
    "title" => "WEEKEND VIBES: Nonton Berdua Lebih Hemat!",
    "category" => "Member",
    "valid" => "Valid until 27/02/2026 - 30/06/2026",
    "img" => "assets/img/promo-weekend.png",
    "featured" => true,
    "hero" => false,
    "cta_link" => "promotions.php#weekend",
  ],
  [
    "id" => "rewind",
    "title" => "REWIND Night: Special Screening & QnA",
    "category" => "Event",
    "valid" => "Valid until 25/02/2026",
    "img" => "assets/img/promo-rewind.png",
    "featured" => false,
    "hero" => false,
    "cta_link" => "promotions.php#rewind",
  ],
];
<?php
// 検索エンジンにインデックスさせない
header('X-Robots-Tag: noindex, nofollow', true);
// 募集要項ページにリダイレクト
header('Location: requirements/', true, 301);
exit;

<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        jsonResponse(['ok'=>false,'message'=>'نام کاربری باید ۳ تا ۲۰ کاراکتر انگلیسی، عدد یا _ باشد'], 422);
    }
    if (strlen($password) < 6) {
        jsonResponse(['ok'=>false,'message'=>'رمز عبور باید حداقل ۶ کاراکتر باشد'], 422);
    }
    $users = db('users');
    foreach ($users as $u) {
        if (strtolower($u['username']) === strtolower($username)) {
            jsonResponse(['ok'=>false,'message'=>'این نام کاربری قبلاً ثبت شده است'], 409);
        }
    }
    $id = bin2hex(random_bytes(16));
    $users[$id] = [
        'id'=>$id, 'username'=>$username,
        'password'=>password_hash($password, PASSWORD_DEFAULT),
        'score'=>0, 'created'=>time()
    ];
    saveDB('users', $users);
    session_regenerate_id(true);
    $_SESSION['user'] = ['id'=>$id,'username'=>$username];
    jsonResponse(['ok'=>true,'username'=>$username]);
}

if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    foreach (db('users') as $u) {
        if (strtolower($u['username']) === strtolower($username) && password_verify($password, $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id'=>$u['id'],'username'=>$u['username']];
            jsonResponse(['ok'=>true,'username'=>$u['username']]);
        }
    }
    jsonResponse(['ok'=>false,'message'=>'نام کاربری یا رمز عبور اشتباه است'], 401);
}

if ($action === 'me') {
    jsonResponse(['ok'=>true,'user'=>$_SESSION['user'] ?? null]);
}

if ($action === 'create_room') {
    $user = requireUser();
    $rooms = db('rooms');
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    } while (isset($rooms[$code]));
    $map = (string)($_POST['map'] ?? 'forest');
    if (!in_array($map, ['graveyard','forest','village','city','military','hospital'], true)) $map = 'forest';
    $rooms[$code] = [
        'host'=>$user['id'], 'created'=>time(), 'map'=>$map,
        'players'=>[
            $user['id']=>[
                'username'=>$user['username'], 'x'=>2000, 'y'=>2000,
                'health'=>100, 'last_seen'=>time()
            ]
        ]
    ];
    saveDB('rooms', $rooms);
    jsonResponse(['ok'=>true,'code'=>$code,'map'=>$map]);
}

if ($action === 'join_room') {
    $user = requireUser();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $rooms = db('rooms');
    if (!preg_match('/^[A-Z0-9]{6}$/', $code) || !isset($rooms[$code])) {
        jsonResponse(['ok'=>false,'message'=>'اتاق پیدا نشد'], 404);
    }
    foreach ($rooms[$code]['players'] as $id=>$p) {
        if (($p['last_seen'] ?? 0) < time()-30) unset($rooms[$code]['players'][$id]);
    }
    if (!isset($rooms[$code]['players'][$user['id']]) && count($rooms[$code]['players']) >= 6) {
        jsonResponse(['ok'=>false,'message'=>'ظرفیت اتاق تکمیل است'], 409);
    }
    $rooms[$code]['players'][$user['id']] = [
        'username'=>$user['username'], 'x'=>2000, 'y'=>2000,
        'health'=>100, 'last_seen'=>time()
    ];
    saveDB('rooms', $rooms);
    jsonResponse(['ok'=>true,'code'=>$code,'map'=>$rooms[$code]['map'] ?? 'forest']);
}

if ($action === 'sync') {
    $user = requireUser();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $rooms = db('rooms');
    if (!isset($rooms[$code]['players'][$user['id']])) {
        jsonResponse(['ok'=>false,'message'=>'عضویت اتاق پیدا نشد'], 404);
    }
    foreach ($rooms[$code]['players'] as $id=>$p) {
        if (($p['last_seen'] ?? 0) < time()-30) unset($rooms[$code]['players'][$id]);
    }
    if (!isset($rooms[$code]['players'][$user['id']])) {
        jsonResponse(['ok'=>false,'message'=>'اتصال اتاق منقضی شده است'], 404);
    }
    $rooms[$code]['players'][$user['id']]['x'] = max(24,min(3976,(float)($_POST['x'] ?? 2000)));
    $rooms[$code]['players'][$user['id']]['y'] = max(24,min(3976,(float)($_POST['y'] ?? 2000)));
    $rooms[$code]['players'][$user['id']]['health'] = max(0,min(100,(int)($_POST['health'] ?? 100)));
    $rooms[$code]['players'][$user['id']]['last_seen'] = time();
    $players = array_values($rooms[$code]['players']);
    saveDB('rooms', $rooms);
    jsonResponse(['ok'=>true,'players'=>$players]);
}

if ($action === 'leave_room') {
    $user = requireUser();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $rooms = db('rooms');
    if (isset($rooms[$code])) {
        unset($rooms[$code]['players'][$user['id']]);
        if (!$rooms[$code]['players']) unset($rooms[$code]);
        saveDB('rooms', $rooms);
    }
    jsonResponse(['ok'=>true]);
}

if ($action === 'score') {
    $user = requireUser();
    $score = max(0,min(1000000,(int)($_POST['score'] ?? 0)));
    $users = db('users');
    if (isset($users[$user['id']])) {
        $users[$user['id']]['score'] = max((int)$users[$user['id']]['score'], $score);
        saveDB('users', $users);
    }
    jsonResponse(['ok'=>true]);
}

jsonResponse(['ok'=>false,'message'=>'درخواست نامعتبر است'], 400);

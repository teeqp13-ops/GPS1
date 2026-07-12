<?php
require_once dirname(__DIR__).'/bootstrap.php';
session_name(SESSION_NAME); session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (!($_SESSION['ok'] ?? false)) {
    $err='';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (hash_equals(ADMIN_USERNAME,$_POST['u']??'') && hash_equals(ADMIN_PASSWORD,$_POST['p']??'')) {
            $_SESSION['ok']=true; header('Location:index.php'); exit;
        }
        $err='بيانات الدخول غير صحيحة';
    }
    ?><!doctype html><html dir="rtl" lang="ar"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>دخول الإدارة</title><style>body{font-family:Arial;background:#0f172a;color:#fff;display:grid;place-items:center;height:100vh;margin:0}.box{background:#1e293b;padding:28px;border-radius:18px;width:min(360px,85%)}input,button{width:100%;padding:13px;margin:7px 0;border-radius:10px;border:0;box-sizing:border-box}button{background:#2563eb;color:#fff;font-weight:bold}.e{color:#fca5a5}</style><div class="box"><h2>لوحة GPSPlus</h2><div class="e"><?=htmlspecialchars($err)?></div><form method="post"><input name="u" placeholder="اسم المستخدم"><input name="p" type="password" placeholder="كلمة المرور"><button>دخول</button></form></div></html><?php exit;
}
$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act=$_POST['act']??'';
    if ($act==='generate') {
        $count=max(1,min(500,(int)($_POST['count']??1)));
        $days=max(1,(int)($_POST['days']??30));
        $prefix=preg_replace('/[^A-Z0-9-]/','',strtoupper($_POST['prefix']??'GPS-'));
        $st=$pdo->prepare("INSERT INTO licenses(code,duration_days,status,created_at,notes) VALUES(?,?,'unused',?,?)");
        for($i=0;$i<$count;$i++){
            do{
                $code=$prefix.strtoupper(substr(bin2hex(random_bytes(6)),0,10));
                try{$st->execute([$code,$days,now(),trim($_POST['notes']??'')]);$ok=true;}catch(Throwable $e){$ok=false;}
            }while(!$ok);
        }
    }
    if ($act==='toggle') $pdo->prepare("UPDATE licenses SET status=CASE WHEN status='disabled' THEN CASE WHEN device_uuid IS NULL THEN 'unused' ELSE 'active' END ELSE 'disabled' END WHERE id=?")->execute([(int)$_POST['id']]);
    if ($act==='unbind') $pdo->prepare("UPDATE licenses SET device_uuid=NULL,device_name=NULL,ios_version=NULL,app_version=NULL,bundle_id=NULL,activated_at=NULL,expires_at=NULL,last_seen_at=NULL,status='unused' WHERE id=?")->execute([(int)$_POST['id']]);
    if ($act==='delete') $pdo->prepare('DELETE FROM licenses WHERE id=?')->execute([(int)$_POST['id']]);
    header('Location:index.php'); exit;
}
$rows=$pdo->query('SELECT * FROM licenses ORDER BY id DESC LIMIT 500')->fetchAll();
?><!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>GPSPlus Admin</title><style>body{font-family:Arial;background:#f1f5f9;margin:0;color:#0f172a}header{background:#0f172a;color:white;padding:18px;display:flex;justify-content:space-between}.wrap{max-width:1200px;margin:auto;padding:18px}.panel{background:white;padding:16px;border-radius:14px;box-shadow:0 3px 12px #0001;margin-top:14px}input,select,button{padding:10px;border:1px solid #cbd5e1;border-radius:8px}button{cursor:pointer}.primary{background:#2563eb;color:white;border:0}.danger{background:#dc2626;color:white;border:0}.warn{background:#f59e0b;border:0}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap}.scroll{overflow:auto}</style></head><body><header><b>GPSPlus — إدارة الأكواد</b><a href="?logout=1" style="color:white">خروج</a></header><div class="wrap"><div class="panel"><h3>إنشاء أكواد</h3><form method="post"><input type="hidden" name="act" value="generate"><input name="prefix" value="GPS-"><input name="count" type="number" value="1" min="1" max="500"><select name="days"><option value="1">يوم</option><option value="30" selected>شهر</option><option value="90">3 أشهر</option><option value="180">6 أشهر</option><option value="365">سنة</option><option value="730">سنتان</option></select><input name="notes" placeholder="ملاحظة"><button class="primary">إنشاء</button></form></div><div class="panel scroll"><table><thead><tr><th>#</th><th>الكود</th><th>الحالة</th><th>المدة</th><th>الجهاز</th><th>الانتهاء</th><th>التحكم</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=$r['id']?></td><td><b><?=htmlspecialchars($r['code'])?></b></td><td><?=htmlspecialchars($r['status'])?></td><td><?=$r['duration_days']?> يوم</td><td><?=htmlspecialchars($r['device_name']?:$r['device_uuid']?:'-')?></td><td><?=$r['expires_at']?:'-'?></td><td><form method="post" style="display:inline"><input type="hidden" name="id" value="<?=$r['id']?>"><button name="act" value="toggle" class="warn">إيقاف/تشغيل</button><button name="act" value="unbind">فك الجهاز</button><button name="act" value="delete" class="danger" onclick="return confirm('حذف؟')">حذف</button></form></td></tr><?php endforeach?></tbody></table></div></div></body></html>

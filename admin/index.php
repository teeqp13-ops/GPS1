<?php
require_once dirname(__DIR__).'/bootstrap.php';
admin_session_start();

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (!($_SESSION['ok'] ?? false)) {
    $err='';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (hash_equals(admin_username(), (string)($_POST['u']??'')) && hash_equals(admin_password(), (string)($_POST['p']??''))) {
            session_regenerate_id(true); $_SESSION['ok']=true; csrf_token(); header('Location:index.php'); exit;
        }
        $err='بيانات الدخول غير صحيحة';
    }
    ?><!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>دخول الإدارة</title><style>body{font-family:Arial;background:#0f172a;color:#fff;display:grid;place-items:center;min-height:100vh;margin:0}.box{background:#1e293b;padding:28px;border-radius:18px;width:min(360px,85%);box-shadow:0 20px 60px #0005}input,button{width:100%;padding:13px;margin:7px 0;border-radius:10px;border:0;box-sizing:border-box}button{background:#2563eb;color:#fff;font-weight:bold}.e{color:#fca5a5}</style></head><body><div class="box"><h2>لوحة GPSPlus</h2><div class="e"><?=htmlspecialchars($err)?></div><form method="post"><input name="u" autocomplete="username" placeholder="اسم المستخدم" required><input name="p" type="password" autocomplete="current-password" placeholder="كلمة المرور" required><button>دخول</button></form></div></body></html><?php exit;
}

$pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $act=$_POST['act']??'';
    if ($act==='generate') {
        $count=max(1,min(500,(int)($_POST['count']??1)));
        $days=max(1,min(3650,(int)($_POST['days']??30)));
        $prefix=preg_replace('/[^A-Z0-9-]/','',strtoupper((string)($_POST['prefix']??'GPS-'))) ?: 'GPS-';
        $st=$pdo->prepare("INSERT INTO licenses(code,duration_days,status,created_at,notes) VALUES(?,?,'unused',?,?)");
        for($i=0;$i<$count;$i++){
            do { $code=$prefix.strtoupper(substr(bin2hex(random_bytes(8)),0,12)); try{$st->execute([$code,$days,now(),trim((string)($_POST['notes']??''))]);$ok=true;}catch(Throwable $e){$ok=false;} } while(!$ok);
        }
    } elseif ($act==='toggle') {
        $pdo->prepare("UPDATE licenses SET status=CASE WHEN status='disabled' THEN CASE WHEN device_uuid IS NULL THEN 'unused' ELSE 'active' END ELSE 'disabled' END WHERE id=?")->execute([(int)$_POST['id']]);
    } elseif ($act==='unbind') {
        $pdo->prepare("UPDATE licenses SET device_uuid=NULL,device_name=NULL,ios_version=NULL,app_version=NULL,bundle_id=NULL,activated_at=NULL,expires_at=NULL,last_seen_at=NULL,status='unused' WHERE id=?")->execute([(int)$_POST['id']]);
    } elseif ($act==='delete') {
        $pdo->prepare('DELETE FROM licenses WHERE id=?')->execute([(int)$_POST['id']]);
    } elseif ($act==='clear_logs') {
        $pdo->exec('DELETE FROM api_logs');
    }
    header('Location:index.php'); exit;
}

$search=trim((string)($_GET['q']??''));
if($search!==''){$st=$pdo->prepare('SELECT * FROM licenses WHERE code LIKE ? OR device_uuid LIKE ? OR notes LIKE ? ORDER BY id DESC LIMIT 500');$like='%'.$search.'%';$st->execute([$like,$like,$like]);$rows=$st->fetchAll();}else{$rows=$pdo->query('SELECT * FROM licenses ORDER BY id DESC LIMIT 500')->fetchAll();}
$stats=$pdo->query("SELECT COUNT(*) total,SUM(status='unused') unused,SUM(status='active') active,SUM(status='disabled') disabled,SUM(status='expired') expired FROM licenses")->fetch() ?: [];
$logs=$pdo->query('SELECT * FROM api_logs ORDER BY id DESC LIMIT 100')->fetchAll();
$csrf=csrf_token();
?><!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GPSPlus Admin</title><style>body{font-family:Arial;background:#f1f5f9;margin:0;color:#0f172a}header{background:#0f172a;color:#fff;padding:18px;display:flex;justify-content:space-between;gap:12px;align-items:center}.wrap{max-width:1250px;margin:auto;padding:18px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.card,.panel{background:#fff;padding:16px;border-radius:14px;box-shadow:0 3px 12px #0001;margin-top:14px}.card b{font-size:24px;display:block}input,select,button{padding:10px;border:1px solid #cbd5e1;border-radius:8px}button{cursor:pointer}.primary{background:#2563eb;color:#fff;border:0}.danger{background:#dc2626;color:#fff;border:0}.warn{background:#f59e0b;border:0}.ok{background:#16a34a;color:#fff;border:0}.toolbar{display:flex;gap:8px;flex-wrap:wrap}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:9px;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap}.badge{padding:4px 8px;border-radius:999px;background:#e2e8f0}@media(max-width:760px){.grid{grid-template-columns:1fr 1fr}header{align-items:flex-start}.wrap{padding:10px}}</style></head><body><header><div><b>GPSPlus — إدارة الأكواد</b><small style="display:block;opacity:.7">الإصدار <?=htmlspecialchars(APP_VERSION)?></small></div><a href="?logout=1" style="color:#fff">خروج</a></header><div class="wrap"><div class="grid"><div class="card">الإجمالي<b><?=intval($stats['total']??0)?></b></div><div class="card">نشط<b><?=intval($stats['active']??0)?></b></div><div class="card">غير مستخدم<b><?=intval($stats['unused']??0)?></b></div><div class="card">موقوف/منتهي<b><?=intval($stats['disabled']??0)+intval($stats['expired']??0)?></b></div></div><div class="panel"><h3>إنشاء أكواد</h3><form method="post" class="toolbar"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="act" value="generate"><input name="prefix" value="GPS-"><input name="count" type="number" value="1" min="1" max="500"><select name="days"><option value="1">يوم</option><option value="30" selected>شهر</option><option value="90">3 أشهر</option><option value="180">6 أشهر</option><option value="365">سنة</option><option value="730">سنتان</option><option value="1095">3 سنوات</option></select><input name="notes" placeholder="ملاحظة"><button class="primary">إنشاء</button></form></div><div class="panel"><form method="get" class="toolbar"><input name="q" value="<?=htmlspecialchars($search)?>" placeholder="بحث بالكود أو الجهاز أو الملاحظة"><button>بحث</button><a href="index.php">إلغاء</a></form></div><div class="panel scroll"><table><thead><tr><th>#</th><th>الكود</th><th>الحالة</th><th>المدة</th><th>الجهاز</th><th>التفعيل</th><th>الانتهاء</th><th>آخر اتصال</th><th>التحكم</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=$r['id']?></td><td><b><?=htmlspecialchars($r['code'])?></b></td><td><span class="badge"><?=htmlspecialchars($r['status'])?></span></td><td><?=$r['duration_days']?> يوم</td><td><?=htmlspecialchars($r['device_name']?:$r['device_uuid']?:'-')?></td><td><?=htmlspecialchars($r['activated_at']?:'-')?></td><td><?=htmlspecialchars($r['expires_at']?:'-')?></td><td><?=htmlspecialchars($r['last_seen_at']?:'-')?></td><td><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>"><input type="hidden" name="id" value="<?=$r['id']?>"><button name="act" value="toggle" class="warn">إيقاف/تشغيل</button><button name="act" value="unbind">فك الجهاز</button><button name="act" value="delete" class="danger" onclick="return confirm('حذف الكود؟')">حذف</button></form></td></tr><?php endforeach?></tbody></table></div><div class="panel scroll"><div class="toolbar" style="justify-content:space-between"><h3>آخر طلبات API</h3><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)"><button name="act" value="clear_logs" class="danger" onclick="return confirm('مسح السجل؟')">مسح السجل</button></form></div><table><thead><tr><th>الوقت</th><th>النقطة</th><th>الكود</th><th>الجهاز</th><th>IP</th><th>HTTP</th><th>النتيجة</th></tr></thead><tbody><?php foreach($logs as $l):?><tr><td><?=htmlspecialchars($l['created_at'])?></td><td><?=htmlspecialchars($l['endpoint'])?></td><td><?=htmlspecialchars($l['code']?:'-')?></td><td><?=htmlspecialchars($l['device_uuid']?:'-')?></td><td><?=htmlspecialchars($l['ip']?:'-')?></td><td><?=intval($l['http_status'])?></td><td><?=htmlspecialchars($l['response_status']?:'-')?></td></tr><?php endforeach?></tbody></table></div></div></body></html>
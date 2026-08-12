<?php
require __DIR__ . '/src/bootstrap.php';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function input_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function require_fields(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') json_response(['error'=>"Campo requerido: $field"],422);
    }
}
function officer_exists(int $id): bool {
    $s=db()->prepare('SELECT id FROM officers WHERE id=?'); $s->execute([$id]); return (bool)$s->fetchColumn();
}

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') json_response([]);
    $like = '%' . $q . '%';
    $stmt = db()->prepare("SELECT o.id,o.position,o.cedula,o.rank_name,o.first_name,o.last_name,o.status,d.name direction
      FROM officers o LEFT JOIN directions d ON d.id=o.direction_id
      WHERE o.position LIKE ? OR o.cedula LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR CONCAT(o.first_name,' ',o.last_name) LIKE ?
      ORDER BY o.last_name,o.first_name LIMIT 30");
    $stmt->execute([$like,$like,$like,$like,$like]);
    json_response($stmt->fetchAll());
}

if ($action === 'officer') {
    $id=(int)($_GET['id']??0);
    $stmt=db()->prepare("SELECT o.*,d.name direction FROM officers o LEFT JOIN directions d ON d.id=o.direction_id WHERE o.id=?");
    $stmt->execute([$id]); $officer=$stmt->fetch();
    if(!$officer) json_response(['error'=>'Funcionario no encontrado'],404);
    $docs=db()->prepare("SELECT doc.id,doc.document_type_id,doc.document_date,doc.description,doc.original_name,doc.size_bytes,doc.created_at,dt.name document_type
      FROM documents doc JOIN document_types dt ON dt.id=doc.document_type_id WHERE doc.officer_id=? ORDER BY COALESCE(doc.document_date,DATE(doc.created_at)) DESC,doc.id DESC");
    $docs->execute([$id]);
    $types=db()->query("SELECT id,name,required_flag FROM document_types WHERE active=1 ORDER BY name")->fetchAll();
    $required=(int)db()->query("SELECT COUNT(*) FROM document_types WHERE active=1 AND required_flag=1")->fetchColumn();
    $complete=db()->prepare("SELECT COUNT(DISTINCT document_type_id) FROM documents d JOIN document_types t ON t.id=d.document_type_id AND t.required_flag=1 WHERE d.officer_id=?");
    $complete->execute([$id]);
    $have=(int)$complete->fetchColumn();
    json_response(['officer'=>$officer,'documents'=>$docs->fetchAll(),'document_types'=>$types,'progress'=>['required'=>$required,'complete'=>$have,'percent'=>$required?round($have/$required*100):100]]);
}

if ($action === 'lookups') {
    $directions=db()->query("SELECT id,name,code FROM directions WHERE active=1 ORDER BY name")->fetchAll();
    $types=db()->query("SELECT id,name,required_flag,active FROM document_types ORDER BY name")->fetchAll();
    json_response(['directions'=>$directions,'document_types'=>$types]);
}

if ($action === 'dashboard') {
    $totals=db()->query("SELECT COUNT(*) total,
      SUM(EXISTS(SELECT 1 FROM documents x WHERE x.officer_id=o.id)) digitized,
      SUM(NOT EXISTS(SELECT 1 FROM documents x WHERE x.officer_id=o.id)) pending
      FROM officers o")->fetch();
    $docs=(int)db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();
    $rows=db()->query("SELECT d.id,d.name,
      COUNT(o.id) total,
      SUM(EXISTS(SELECT 1 FROM documents x WHERE x.officer_id=o.id)) digitized,
      SUM(NOT EXISTS(SELECT 1 FROM documents x WHERE x.officer_id=o.id)) pending
      FROM directions d LEFT JOIN officers o ON o.direction_id=d.id
      WHERE d.active=1 GROUP BY d.id,d.name ORDER BY d.name")->fetchAll();
    foreach($rows as &$r){$r['percent']=(int)$r['total']?round(((int)$r['digitized']/(int)$r['total'])*100,1):0;}
    json_response(['totals'=>['officers'=>(int)$totals['total'],'digitized'=>(int)$totals['digitized'],'pending'=>(int)$totals['pending'],'documents'=>$docs],'directions'=>$rows]);
}

if ($action === 'officers') {
    $direction=(int)($_GET['direction_id']??0); $status=trim($_GET['status']??''); $q=trim($_GET['q']??'');
    $sql="SELECT o.id,o.position,o.cedula,o.rank_name,o.first_name,o.last_name,o.department,o.status,d.name direction,
      (SELECT COUNT(*) FROM documents x WHERE x.officer_id=o.id) document_count
      FROM officers o LEFT JOIN directions d ON d.id=o.direction_id WHERE 1=1"; $p=[];
    if($direction){$sql.=' AND o.direction_id=?';$p[]=$direction;}
    if($status!==''){$sql.=' AND o.status=?';$p[]=$status;}
    if($q!==''){$sql.=" AND (o.position LIKE ? OR o.cedula LIKE ? OR CONCAT(o.first_name,' ',o.last_name) LIKE ?)";$like="%$q%";$p[]=$like;$p[]=$like;$p[]=$like;}
    $sql.=' ORDER BY o.last_name,o.first_name LIMIT 500';
    $s=db()->prepare($sql);$s->execute($p);json_response($s->fetchAll());
}

if ($action === 'save_officer' && $method === 'POST') {
    $d=input_json(); require_fields($d,['position','first_name','last_name']);
    $id=(int)($d['id']??0); $direction=isset($d['direction_id'])&&$d['direction_id']!==''?(int)$d['direction_id']:null;
    $vals=[trim($d['position']),trim($d['cedula']??'')?:null,trim($d['rank_name']??'')?:null,trim($d['first_name']),trim($d['last_name']),$direction,trim($d['department']??'')?:null,$d['status']??'activo'];
    try{
      if($id){$s=db()->prepare('UPDATE officers SET position=?,cedula=?,rank_name=?,first_name=?,last_name=?,direction_id=?,department=?,status=? WHERE id=?');$vals[]=$id;$s->execute($vals);audit('update','officer',$id,$vals[0]);}
      else{$s=db()->prepare('INSERT INTO officers(position,cedula,rank_name,first_name,last_name,direction_id,department,status) VALUES(?,?,?,?,?,?,?,?)');$s->execute($vals);$id=(int)db()->lastInsertId();audit('create','officer',$id,$vals[0]);}
    }catch(PDOException $e){json_response(['error'=>'Posición o cédula duplicada. Verifique los datos.'],422);}
    json_response(['ok'=>true,'id'=>$id]);
}

if ($action === 'save_direction' && $method === 'POST') {
    $d=input_json(); require_fields($d,['name']); $id=(int)($d['id']??0);$name=trim($d['name']);$code=trim($d['code']??'')?:null;
    try{if($id){$s=db()->prepare('UPDATE directions SET name=?,code=? WHERE id=?');$s->execute([$name,$code,$id]);}else{$s=db()->prepare('INSERT INTO directions(name,code) VALUES(?,?)');$s->execute([$name,$code]);$id=(int)db()->lastInsertId();}}catch(PDOException $e){json_response(['error'=>'Nombre o código de dependencia duplicado'],422);}
    audit($d['id']??0?'update':'create','direction',$id,$name); json_response(['ok'=>true,'id'=>$id]);
}

if ($action === 'upload' && $method === 'POST') {
    global $config;
    $officerId=(int)($_POST['officer_id']??0);$typeId=(int)($_POST['document_type_id']??0);$date=($_POST['document_date']??'')?:null;$description=trim($_POST['description']??'');
    if(!$officerId||!$typeId||empty($_FILES['file']))json_response(['error'=>'Datos incompletos'],422);
    if(!officer_exists($officerId))json_response(['error'=>'Funcionario no encontrado'],404);
    $file=$_FILES['file']; if($file['error']!==UPLOAD_ERR_OK)json_response(['error'=>'No se pudo cargar el archivo'],422);
    if($file['size']>$config['max_upload_bytes'])json_response(['error'=>'El archivo excede el tamaño permitido'],422);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); if($mime!=='application/pdf')json_response(['error'=>'Solo se permiten archivos PDF'],422);
    $dir=rtrim($config['storage_path'],'/\\').DIRECTORY_SEPARATOR.$officerId;if(!is_dir($dir)&&!mkdir($dir,0775,true))json_response(['error'=>'No se pudo crear almacenamiento'],500);
    $stored=bin2hex(random_bytes(16)).'.pdf';$dest=$dir.DIRECTORY_SEPARATOR.$stored;if(!move_uploaded_file($file['tmp_name'],$dest))json_response(['error'=>'No se pudo guardar el archivo'],500);
    $relative=$officerId.'/'.$stored;$s=db()->prepare('INSERT INTO documents(officer_id,document_type_id,original_name,stored_name,mime_type,size_bytes,document_date,description) VALUES(?,?,?,?,?,?,?,?)');$s->execute([$officerId,$typeId,$file['name'],$relative,$mime,$file['size'],$date,$description?:null]);$id=(int)db()->lastInsertId();audit('upload','document',$id,$file['name']);json_response(['ok'=>true,'id'=>$id],201);
}

if ($action === 'update_document' && $method === 'POST') {
    $d=input_json();$id=(int)($d['id']??0);$type=(int)($d['document_type_id']??0);if(!$id||!$type)json_response(['error'=>'Datos incompletos'],422);
    $s=db()->prepare('UPDATE documents SET document_type_id=?,document_date=?,description=? WHERE id=?');$s->execute([$type,($d['document_date']??'')?:null,trim($d['description']??'')?:null,$id]);audit('update','document',$id,'Metadatos actualizados');json_response(['ok'=>true]);
}

if ($action === 'delete_document' && $method === 'POST') {
    global $config;$d=input_json();$id=(int)($d['id']??0);$s=db()->prepare('SELECT original_name,stored_name FROM documents WHERE id=?');$s->execute([$id]);$doc=$s->fetch();if(!$doc)json_response(['error'=>'Documento no encontrado'],404);
    $path=rtrim($config['storage_path'],'/\\').DIRECTORY_SEPARATOR.str_replace(['../','..\\'],'',$doc['stored_name']);
    db()->prepare('DELETE FROM documents WHERE id=?')->execute([$id]);if(is_file($path))@unlink($path);audit('delete','document',$id,$doc['original_name']);json_response(['ok'=>true]);
}

if ($action === 'import_officers' && $method === 'POST') {
    if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)json_response(['error'=>'Seleccione un archivo CSV'],422);
    $fh=fopen($_FILES['file']['tmp_name'],'r');if(!$fh)json_response(['error'=>'No se pudo leer el archivo'],422);
    $header=fgetcsv($fh);if(!$header)json_response(['error'=>'CSV vacío'],422);$header=array_map(fn($x)=>strtolower(trim($x)),$header);
    $required=['position','first_name','last_name'];foreach($required as $r)if(!in_array($r,$header,true))json_response(['error'=>'Falta columna '.$r],422);
    $map=array_flip($header);$inserted=0;$updated=0;$errors=[];$line=1;
    while(($row=fgetcsv($fh))!==false){$line++;if(count($row)<count($header))$row=array_pad($row,count($header),'');$get=fn($k)=>isset($map[$k])?trim($row[$map[$k]]??''):'';$position=$get('position');if($position==='')continue;
      $directionId=null;$direction=$get('direction');if($direction!==''){$s=db()->prepare('SELECT id FROM directions WHERE name=?');$s->execute([$direction]);$directionId=$s->fetchColumn();if(!$directionId){$s=db()->prepare('INSERT INTO directions(name) VALUES(?)');$s->execute([$direction]);$directionId=(int)db()->lastInsertId();}}
      try{$s=db()->prepare('SELECT id FROM officers WHERE position=?');$s->execute([$position]);$id=$s->fetchColumn();$vals=[$get('cedula')?:null,$get('rank_name')?:null,$get('first_name'),$get('last_name'),$directionId,$get('department')?:null,$get('status')?:'activo'];
        if($id){$u=db()->prepare('UPDATE officers SET cedula=?,rank_name=?,first_name=?,last_name=?,direction_id=?,department=?,status=? WHERE id=?');$vals[]=$id;$u->execute($vals);$updated++;}
        else{$u=db()->prepare('INSERT INTO officers(position,cedula,rank_name,first_name,last_name,direction_id,department,status) VALUES(?,?,?,?,?,?,?,?)');$u->execute(array_merge([$position],$vals));$inserted++;}
      }catch(Throwable $e){$errors[]="Línea $line: $position";}
    }fclose($fh);audit('import','officer',null,"Insertados $inserted, actualizados $updated");json_response(['ok'=>true,'inserted'=>$inserted,'updated'=>$updated,'errors'=>$errors]);
}

if ($action === 'document') {
    global $config;$id=(int)($_GET['id']??0);$s=db()->prepare('SELECT original_name,stored_name FROM documents WHERE id=?');$s->execute([$id]);$doc=$s->fetch();if(!$doc){http_response_code(404);exit('Documento no encontrado');}
    $path=rtrim($config['storage_path'],'/\\').DIRECTORY_SEPARATOR.str_replace(['../','..\\'],'',$doc['stored_name']);if(!is_file($path)){http_response_code(404);exit('Archivo no disponible');}
    audit('preview','document',$id,$doc['original_name']);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.rawurlencode($doc['original_name']).'"');header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');readfile($path);exit;
}

json_response(['error'=>'Acción no válida'],404);

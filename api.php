<?php
require __DIR__ . '/src/bootstrap.php';
$action = $_GET['action'] ?? '';

if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') json_response([]);
    $like = '%' . $q . '%';
    $stmt = db()->prepare("SELECT o.id,o.position,o.cedula,o.rank_name,o.first_name,o.last_name,o.status,d.name direction
        FROM officers o LEFT JOIN directions d ON d.id=o.direction_id
        WHERE o.position LIKE ? OR o.cedula LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ? OR CONCAT(o.first_name,' ',o.last_name) LIKE ?
        ORDER BY o.last_name,o.first_name LIMIT 25");
    $stmt->execute([$like,$like,$like,$like,$like]);
    json_response($stmt->fetchAll());
}

if ($action === 'officer') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT o.*,d.name direction FROM officers o LEFT JOIN directions d ON d.id=o.direction_id WHERE o.id=?");
    $stmt->execute([$id]);
    $officer = $stmt->fetch();
    if (!$officer) json_response(['error'=>'Funcionario no encontrado'],404);
    $docs = db()->prepare("SELECT doc.id,doc.document_date,doc.description,doc.original_name,doc.size_bytes,doc.created_at,dt.name document_type
      FROM documents doc JOIN document_types dt ON dt.id=doc.document_type_id WHERE doc.officer_id=? ORDER BY COALESCE(doc.document_date,DATE(doc.created_at)) DESC,doc.id DESC");
    $docs->execute([$id]);
    $types = db()->query("SELECT id,name FROM document_types WHERE active=1 ORDER BY name")->fetchAll();
    json_response(['officer'=>$officer,'documents'=>$docs->fetchAll(),'document_types'=>$types]);
}

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    global $config;
    $officerId = (int)($_POST['officer_id'] ?? 0);
    $typeId = (int)($_POST['document_type_id'] ?? 0);
    $date = $_POST['document_date'] ?: null;
    $description = trim($_POST['description'] ?? '');
    if (!$officerId || !$typeId || empty($_FILES['file'])) json_response(['error'=>'Datos incompletos'],422);
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) json_response(['error'=>'No se pudo cargar el archivo'],422);
    if ($file['size'] > $config['max_upload_bytes']) json_response(['error'=>'El archivo excede el tamaño permitido'],422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== 'application/pdf') json_response(['error'=>'Solo se permiten archivos PDF'],422);
    $dir = rtrim($config['storage_path'],'/\\') . DIRECTORY_SEPARATOR . $officerId;
    if (!is_dir($dir) && !mkdir($dir,0775,true)) json_response(['error'=>'No se pudo crear el directorio de almacenamiento'],500);
    $stored = bin2hex(random_bytes(16)) . '.pdf';
    $dest = $dir . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($file['tmp_name'],$dest)) json_response(['error'=>'No se pudo guardar el archivo'],500);
    $relative = $officerId . '/' . $stored;
    $stmt = db()->prepare('INSERT INTO documents(officer_id,document_type_id,original_name,stored_name,mime_type,size_bytes,document_date,description) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->execute([$officerId,$typeId,$file['name'],$relative,$mime,$file['size'],$date,$description ?: null]);
    $id = (int)db()->lastInsertId();
    audit('upload','document',$id,$file['name']);
    json_response(['ok'=>true,'id'=>$id],201);
}

if ($action === 'document') {
    global $config;
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT original_name,stored_name,mime_type FROM documents WHERE id=?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) { http_response_code(404); exit('Documento no encontrado'); }
    $path = rtrim($config['storage_path'],'/\\') . DIRECTORY_SEPARATOR . str_replace(['../','..\\'],'',$doc['stored_name']);
    if (!is_file($path)) { http_response_code(404); exit('Archivo no disponible'); }
    audit('preview','document',$id,$doc['original_name']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . rawurlencode($doc['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

json_response(['error'=>'Acción no válida'],404);

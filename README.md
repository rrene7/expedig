# EXPEDIG

Sistema web sencillo para digitalizar y consultar expedientes de seguro privado de unidades policiales. Esta versión funciona **sin autenticación** y está pensada para XAMPP + PHP + MySQL/MariaDB en una red interna.

## Funciones incluidas

- Dashboard de avance de digitalización por Dirección/Zona.
- Búsqueda por posición, cédula, nombre o apellido.
- Expediente individual por funcionario.
- Carga de documentos PDF.
- Vista previa del PDF al presionar el tipo de documento.
- Eliminación controlada del documento y del archivo físico.
- Registro y edición de funcionarios.
- Registro y edición de Direcciones/Zonas.
- Importación masiva de funcionarios desde CSV.
- Indicador de avance de documentos requeridos.
- Bitácora técnica por IP, sin usuarios ni contraseñas.

## Instalación en XAMPP

1. Copiar/clonar el proyecto en `C:\xampp\htdocs\expedig`.
2. Crear `config.php` copiando `config.example.php`.
3. Importar `database/schema.sql` en phpMyAdmin o MySQL.
4. Verificar que Apache y MySQL estén activos.
5. Abrir `http://localhost/expedig`.

La configuración de ejemplo usa MySQL en `127.0.0.1:3306`, usuario `root`, contraseña vacía y base `expedig`.

## Importación masiva

Use `import-template.csv` como plantilla. Encabezados:

`position,cedula,rank_name,first_name,last_name,direction,department,status`

`position`, `first_name` y `last_name` son obligatorios. La posición es única: si ya existe, la importación actualiza el funcionario.

## PDF

Los PDF se almacenan en `storage/private/<id_funcionario>/`. La carpeta incluye reglas para impedir acceso HTTP directo; el visor accede a los archivos solamente mediante `api.php?action=document&id=...`.

Tamaño máximo por PDF: 20 MB, configurable en `config.php`.

## Sin autenticación

No existen tabla de usuarios, login, contraseña, roles ni sesiones. El sistema abre directamente en el dashboard. Para un despliegue institucional, debe mantenerse dentro de una red controlada y limitarse el acceso al servidor mediante red/firewall.

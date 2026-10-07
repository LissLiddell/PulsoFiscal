# Pulso Fiscal — demo de resumen de facturación

Proyecto de portafolio con **Laravel 13, Livewire 4 y PostgreSQL**. La interfaz y las reglas se ejecutan en una sola aplicación PHP; la base puede alojarse en Neon y la aplicación en un servicio web Docker de Render. **Todavía no está publicada.**

La demo muestra facturas y pagos completamente ficticios, calcula importes en centavos enteros y ofrece una comprobación interactiva de datos de muestra. Esa comprobación es solo una simulación: **no emite, timbra, valida ni cancela CFDI y no se conecta al SAT ni a un PAC**. No uses RFC, XML, clientes o credenciales reales.

## Lo que ya incluye

- Tablero con emitido vigente, cobrado, saldo abierto y vencido a la fecha de consulta.
- Catálogo de ancho completo, buscable y filtrable por estado. Al elegir una factura se abre un modal con detalle de cobro a la izquierda y trazabilidad documental a la derecha; en celular se apilan.
- Un botón en el catálogo abre el modal de importación CSV ficticia. Ahí se elige un archivo o se pega su contenido, se revisan filas, errores y duplicados, y la vista previa calcula cuánto cambiarían los indicadores. Las facturas aceptadas viven solo en la sesión, se pueden retirar y admiten los mismos abonos ficticios. El catálogo filtrado se puede exportar a CSV.
- Abonos ficticios con importe y fecha: actualizan indicadores, saldo y estado en la sesión de cada visitante; se pueden reiniciar sin alterar PostgreSQL.
- Vista según el estado: las facturas con saldo admiten abonos de prueba (incluso si están vencidas); las pagadas muestran el cierre del cobro y una próxima etapa documental sin emisión; las canceladas quedan para consulta.
- Ficha documental ficticia separada del estado de cobro: método PUE/PPD de muestra, estado de datos y línea de tiempo con pagos. Los ejemplos PPD muestran un seguimiento pendiente por cada cobro; el ejemplo PUE pagado al registrar no muestra complemento. Nada de esto produce documentos fiscales.
- Comprobación técnica de datos de muestra para registros vigentes con saldo. Se puede corregir el concepto en pantalla sin guardar el cambio ni escribir un CFDI.
- Datos de muestra idempotentes y pruebas de cálculos, fecha de corte, simulación y sembrado.
- Interfaz pública sin escrituras en la base: el escenario de abonos vive temporalmente en la sesión del visitante. No mueve dinero ni emite complementos fiscales.

## Probar la importación CSV

En el catálogo, pulsa **Importar facturas ficticias** para abrir el modal. Descarga el archivo de ejemplo o pega un CSV UTF-8 cuya primera línea sea exactamente `referencia,cliente,concepto,emision,vencimiento,importe,estado`, en ese orden y separada por comas. Cada línea posterior es una factura; por ejemplo: `EJ-901,Cliente de prueba,Servicio de muestra,2026-10-01,2026-10-07,1250.00,vigente`. La emisión no puede ser futura, el vencimiento debe ser igual o posterior, y el importe debe ser positivo, sin `$` ni separador de miles, con punto decimal. El estado admite `vigente` o `cancelada`. Cada archivo admite hasta 50 filas, 100 facturas importadas por sesión y 100 KB. Las referencias no pueden repetirse entre el CSV, la demo original y lo ya importado. Se aceptan solo facturas PPD ficticias sin pagos iniciales; no se suben ni generan CFDI.

Revisa la vista previa y confirma las filas válidas. **Quitar importación de prueba** retira esas facturas y sus abonos simulados; no borra los datos precargados. **Exportar vista CSV** descarga las filas que coinciden con la búsqueda y el filtro actuales. Ese archivo es un reporte distinto de la plantilla de carga: incluye importes con `$` y separador de miles para su lectura, además de las columnas Cobrado y Saldo; no se reimporta tal cual.

## Arranque local

Requisitos: PHP 8.3 o superior con `mbstring` y `pdo_pgsql`, Composer, PostgreSQL. Para las pruebas aisladas también se requieren `pdo_sqlite` y `sqlite3`. Las dependencias están instaladas localmente, las pruebas aisladas pasaron y la conexión a `facturacion_demo` local quedó verificada. La migración y el sembrado de datos ficticios se ejecutaron correctamente.

1. En esta carpeta ejecuta `composer install`.
2. Copia `.env.example` a `.env`, configura tu PostgreSQL local y ejecuta `php artisan key:generate`.
3. Ejecuta `php artisan migrate --seed` y `php artisan serve`. Si ya habías iniciado la demo, vuelve a ejecutar `php artisan migrate --seed` para agregar y poblar la ficha documental de muestra.
4. Abre `http://localhost:8000`.
5. Ejecuta `php artisan test` para verificar las reglas si `pdo_sqlite` y `sqlite3` están habilitadas en `php.ini`. En Windows también puedes ejecutarlas sin cambiar `php.ini` con `php -d extension=pdo_sqlite -d extension=sqlite3 vendor\bin\phpunit`.

No guardes una conexión Neon real en Git ni pegues sus credenciales en capturas.

## Preparación para Render + Neon

El `Dockerfile` prepara PHP y Apache para un único Web Service; `deploy/start.sh` ejecuta migraciones y un sembrado idempotente al arrancar. Este despliegue usa Docker. `composer.lock` ya está generado y las pruebas aisladas pasaron. La base **Pulso Fiscal Demo** ya está creada en Neon, plan Free, región AWS US East 2 (Ohio); todavía falta conectarla y verificar la aplicación publicada.

Las variables necesarias en Render serían `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generada localmente, `APP_URL` del servicio, `DB_CONNECTION=pgsql`, `DATABASE_URL` de Neon con TLS (`sslmode=require`), `SESSION_DRIVER=file`, `SESSION_SECURE_COOKIE=true` y `CACHE_STORE=file`. Configura la ruta de comprobación de salud como `/up`. La URL de Neon y `APP_KEY` son secretos: solo deben guardarse como variables privadas del servicio; nunca en Git o en capturas.

La demo usa archivos de sesión efímeros, aceptables para esta interacción sin escritura; se pierden al dormir o reiniciar Render. Para usuarios reales o trabajo fiscal se necesitarían autenticación, autorización, privacidad, auditoría y una revisión fiscal independiente.

## Pendiente para una versión ampliada

La integración real de CFDI no forma parte de esta demo y requeriría diseño y validación legal/técnica por separado. La importación actual detecta y omite duplicados; una versión ampliada podría ofrecer conciliación explícita de registros.

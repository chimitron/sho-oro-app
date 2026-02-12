# Progreso del Proyecto SHO — Gestión Márgenes Oro

> Diario de aprendizaje: VS Code + Claude + Desarrollo Web
> Fecha de inicio: 12 febrero 2026

---

## Qué es este proyecto

**SHO** es una mini app web interna para una joyería. Calcula en tiempo real los márgenes de compra y venta de oro por quilates, usando el precio spot del mercado (BID en €/gr).

**Stack elegido:**
- Frontend: HTML + Tailwind CSS + Chart.js
- Backend: PHP puro (sin frameworks)
- Base de datos: SQLite3
- API de precios: freegoldprice.org
- Servidor: Banahosting (hosting compartido)

---

## Entorno de Trabajo

### Herramientas utilizadas

| Herramienta | Para qué sirve |
|---|---|
| **VS Code** | Editor de código principal |
| **Claude Code** | Asistente IA integrado en terminal de VS Code |
| **Extensión SFTP** (Natizyskunk) | Subir archivos al servidor por FTP desde VS Code |
| **Homebrew** | Gestor de paquetes para macOS |
| **PHP local** | Servidor de desarrollo en tu propio Mac |
| **SQLite3** | Base de datos en un único archivo, sin instalar nada extra |

### Cómo instalar el entorno de desarrollo

```bash
# Instalar PHP en Mac (necesario para correr el proyecto en local)
brew install php

# Arrancar el servidor local
php -S localhost:8000

# Probar el cron manualmente
php cron.php

# Ver la base de datos
sqlite3 data/prices.db "SELECT * FROM prices ORDER BY recorded_at DESC LIMIT 10;"
```

---

## Configuración SFTP (VS Code → Banahosting)

Se usó la extensión **SFTP de Natizyskunk** para subir archivos directamente desde VS Code al servidor.

**Pasos realizados:**
1. Instalar extensión: `Cmd+Shift+X` → buscar "SFTP" → instalar la de Natizyskunk
2. Configurar: `Cmd+Shift+P` → "SFTP: Config" → rellenar `.vscode/sftp.json`
3. Subir archivos: clic derecho en carpeta → "Upload Folder"

**Archivos que NO se suben al servidor:**
- `data/` — la base de datos SQLite se crea automáticamente en el servidor
- `.git/` y `.gitignore` — solo para control de versiones local
- `config.php` — contiene credenciales, se sube manualmente por FTP

---

## Archivos del Proyecto

```
oro-app/
├── index.html      # UI principal (precios, gráfica, calculadora)
├── bridge.php      # Proxy CORS — obtiene precio del oro de la API
├── db.php          # Conexión SQLite + funciones de guardar/extraer precios
├── api.php         # Endpoint que devuelve historial de precios (JSON)
├── cron.php        # Script que guarda un precio por hora (ejecutado por cron)
├── config.php      # ⚠️ Credenciales API — NO subir a Git
├── logo.png        # Logo de la app
└── data/
    └── prices.db   # Base de datos SQLite (creada automáticamente)
```

---

## Lo Que Hicimos (y Por Qué)

### 1. Revisión inicial del código PHP

**Qué hicimos:** Leer y analizar `db.php`, `cron.php` y `api.php` antes de tocarlos.

**Por qué:** Nunca modificar código sin leerlo primero. Entender qué hace cada archivo evita romper cosas.

**Lo que encontramos:**
- Los tres archivos sin `declare(strict_types=1)` (buena práctica PHP)
- API key hardcodeada directamente en el código
- `bridge.php` también guardaba en la BD (responsabilidad incorrecta)

---

### 2. Separar las credenciales a `config.php`

**Problema:** La clave de la API estaba escrita en `cron.php` y `bridge.php`:
```php
// MAL — la clave visible en el código
?key=TU_CLAVE_SECRETA_AQUI
```

**Solución:** Crear `config.php` con las constantes y excluirlo del repositorio.
```php
// BIEN — las credenciales en un archivo separado
define('API_KEY', 'tu-clave-aqui');
```

**Por qué importa:** Si subes el código a GitHub con la clave visible, cualquiera puede usarla y agotar tus tokens de API.

**Aprendizaje:** Las credenciales (contraseñas, claves API, tokens) nunca van en el código. Van en archivos de configuración que se excluyen del control de versiones (`.gitignore`).

---

### 3. Añadir `declare(strict_types=1)` a todos los archivos PHP

**Qué hace:** Obliga a PHP a ser estricto con los tipos de datos. Si una función espera un número y recibe texto, lanza un error en lugar de intentar convertirlo silenciosamente.

**Por qué es buena práctica:** Detecta bugs antes de que lleguen a producción.

---

### 4. Bug crítico: `curl_close()` rompía la app

**Síntoma:** La app mostraba "Error Red" y el precio del oro era `0.00€`.

**Causa:** PHP 8.5 muestra un aviso `Deprecated` para `curl_close()`. Este aviso se mezclaba con el JSON de respuesta:
```
<b>Deprecated</b>: Function curl_close()...   ← texto HTML inesperado
{ "GSJM": { "Gold": { ... } } }               ← JSON real
```
JavaScript no podía parsear esa mezcla y fallaba silenciosamente.

**Solución:** Eliminar la llamada a `curl_close()` (ya no tiene efecto en PHP 8+).

**Aprendizaje:** Un aviso de PHP que parece inofensivo puede romper completamente una API JSON si aparece antes del contenido.

---

### 5. Responsabilidades separadas: bridge vs cron

**Problema:** `bridge.php` guardaba precios en la BD cada vez que el navegador cargaba la app. Esto generaba un punto en la gráfica por cada visita, no por cada hora.

**Solución:** `bridge.php` es solo un proxy (recibe → devuelve). Solo `cron.php` guarda precios.

```
bridge.php  →  obtener precio para mostrar en pantalla (tiempo real)
cron.php    →  guardar precio en base de datos (histórico, cada hora)
```

**Aprendizaje:** Cada archivo debe tener una única responsabilidad. Mezclar responsabilidades genera bugs difíciles de detectar.

---

### 6. Instalar PHP en Mac con Homebrew

**Por qué:** Para probar el proyecto en local antes de subirlo al servidor.

```bash
brew install php   # instala PHP 8.5
php -S localhost:8000  # servidor local en http://localhost:8000
```

**Homebrew** es el gestor de paquetes estándar para macOS, equivalente a `apt` en Linux.

---

### 7. Deploy en Banahosting

**Pasos realizados:**
1. Subir archivos por FTP (extensión SFTP de VS Code)
2. Subir `config.php` manualmente (no va a Git)
3. La carpeta `data/` se creó sola al primer acceso
4. Configurar cron job en cPanel: `0 * * * * php /home/usuario/superhiperoro.com/oro/cron.php`

**Cron job explicado:**
```
0 * * * *  →  "en el minuto 0 de cada hora" = cada hora en punto
php /ruta/cron.php  →  ejecuta el script
```

---

### 8. Añadir precio de la plata

**Descubrimiento:** La misma llamada a la API que devuelve el oro también devuelve la plata, sin coste adicional.

**Implementación:**
- Nueva función `findSilverBid()` en JavaScript (paralela a `findGoldBid()`)
- Badge visual discreto debajo del precio del oro principal
- El oro es la información principal (tipografía grande), la plata es secundaria (badge pequeño)

---

### 9. Hora de última sincronización

Cuando el precio carga correctamente, el badge de estado muestra la hora:

```
● Sincronizado · 14:32
```

Se genera en JavaScript con `new Date().toLocaleTimeString()` en el momento de la sincronización exitosa.

---

### 10. Git, GitHub y el incidente de seguridad (lección importante)

Esta sección merece detalle porque enseña más que muchos tutoriales.

#### Qué es Git y para qué sirve

**Git** es un sistema de control de versiones. Guarda una foto del proyecto cada vez que haces un commit. Puedes volver atrás en el tiempo, ver qué cambió y por qué.

**GitHub** es un servicio en la nube que guarda esas fotos online. Es como un Google Drive para código, pero mucho más potente.

#### Cómo lo configuramos

```bash
# Instalar la herramienta de GitHub para terminal
brew install gh

# Autenticarse con tu cuenta de GitHub (abre el navegador)
gh auth login

# Configurar tu identidad en Git
git config --global user.name "chimi"
git config --global user.email "chimi@chimi.es"

# Inicializar el repositorio en el proyecto
git init

# Añadir los archivos al siguiente commit (respetando .gitignore)
git add index.html bridge.php db.php api.php cron.php logo.png README.md PROGRESO.md .gitignore

# Crear el commit
git commit -m "Primer commit — descripción del cambio"

# Crear el repositorio en GitHub y subir el código
gh repo create sho-oro-app --public --source=. --remote=origin --push
```

#### El incidente: una clave secreta en el historial de Git

**Qué pasó:** Al escribir el PROGRESO.md se incluyó la clave real de la API como ejemplo de "cómo no hacerlo". Se hizo el commit y se subió a GitHub.

**Por qué fue un problema:** Aunque en el siguiente commit se corrigió el texto con un placeholder, **Git guarda todos los cambios para siempre**. El commit original seguía siendo visible públicamente:

```bash
# Cualquier persona podía ejecutar esto y ver la clave
git show 4ac0ca7 -- PROGRESO.md
```

**Cómo se resolvió:**

La solución fue reescribir completamente el historial de Git — borrar los commits anteriores y crear uno nuevo limpio que nunca había contenido la clave:

```bash
# 1. Borrar todo el historial de Git (solo los commits, no los archivos)
rm -rf .git

# 2. Inicializar repo de cero
git init

# 3. Añadir solo los archivos ya limpios
git add ...

# 4. Crear el único commit limpio
git commit -m "Primer commit limpio"

# 5. Forzar la sobreescritura en GitHub (reemplaza el historial remoto)
git push --force origin main
```

El `--force` en el push es una operación destructiva que normalmente hay que evitar en proyectos con más personas. En este caso era la solución correcta porque el historial contaminado era el problema.

**Aprendizaje clave:** En Git, "corrijo el error en el siguiente commit" no basta cuando el error es una credencial expuesta. Hay que reescribir la historia. Y la mejor solución es no llegar ahí: **revisar siempre qué va en cada commit antes de hacerlo**.

---

### 11. Cómo trabajar con Claude Code desde VS Code

#### Qué es Claude Code

Claude Code es un agente de IA de Anthropic que vive en la terminal de VS Code. No es un chatbot — es un agente que puede leer archivos, ejecutar comandos, editar código y conectarse a servicios externos, todo dentro del proyecto.

#### Skills: conocimiento especializado bajo demanda

Los **skills** son módulos de conocimiento que Claude carga cuando los necesita. En este proyecto hay dos activos:

- **`php-basics`** — Se activa al trabajar con archivos PHP. Contiene patrones, buenas prácticas y ejemplos específicos para hosting compartido sin frameworks.
- **`sqlite-basics`** — Se activa al trabajar con bases de datos. Contiene cómo usar SQLite3 en PHP de forma segura.

Los skills evitan tener que repetir el contexto en cada sesión. Claude sabe cómo trabaja este proyecto específico.

#### Cómo se trabajó en esta sesión

El flujo fue siempre el mismo:

1. **Describir el problema o el objetivo** en lenguaje natural
2. **Claude lee el código** antes de proponer cambios
3. **Claude explica** qué va a hacer y por qué
4. **Se revisa** antes de aplicar
5. **Se prueba en local** antes de subir al servidor
6. **Se despliega** via FTP al servidor de producción

Esto es diferente a simplemente "pedirle código a la IA". El agente tiene contexto del proyecto completo, recuerda decisiones anteriores y detecta inconsistencias entre archivos.

#### Lo que aprendí sobre trabajar con IA en desarrollo

- La IA es más útil cuando el desarrollador entiende lo que está pidiendo, aunque no sepa cómo implementarlo
- Revisar el código que propone enseña más que copiarlo sin más
- Los errores que comete la IA (como el de la API key en el PROGRESO.md) son oportunidades de aprendizaje sobre seguridad y buenas prácticas
- La combinación VS Code + Claude Code convierte la terminal en un entorno de desarrollo guiado

---

## Estado Actual del Proyecto

- [x] App funciona en local (`http://localhost:8000`)
- [x] App funciona en producción (`https://superhiperoro.com/oro/`)
- [x] Precios del oro por quilates (24KT, 21KT, 18KT, 14KT, 9KT)
- [x] Precio de la plata en tiempo real
- [x] Gráfica de evolución 7 días
- [x] Cron guardando un precio por hora
- [x] Calculadora de liquidación por peso
- [x] Hora de última sincronización visible
- [x] Logo e icono de la app
- [x] Repositorio Git inicializado y subido a GitHub
- [x] Historial de Git limpio (sin credenciales expuestas)

---

## Conceptos Aprendidos

| Concepto | Qué es |
|---|---|
| **PHP strict types** | Hace que PHP sea estricto con los tipos de datos |
| **CORS proxy** | Intermediario que permite al navegador llamar a APIs externas |
| **Cron job** | Tarea programada que se ejecuta automáticamente en el servidor |
| **SQLite** | Base de datos en un solo archivo, sin servidor de BD separado |
| **FTP** | Protocolo para transferir archivos al servidor web |
| **API key** | Clave secreta para autenticarse en servicios externos |
| **.gitignore** | Archivo que le dice a Git qué archivos ignorar |
| **BID price** | Precio al que el mercado compra (el más favorable para vender) |
| **Git** | Sistema de control de versiones — guarda el historial de cambios del código |
| **GitHub** | Plataforma online para alojar repositorios Git y colaborar |
| **git commit** | Foto del estado del proyecto en un momento concreto |
| **git push --force** | Sobreescribe el historial remoto — operación destructiva, usar con cuidado |
| **Claude Code** | Agente IA de Anthropic integrado en VS Code con acceso al proyecto completo |
| **Skills (Claude)** | Módulos de conocimiento especializado que Claude carga según el contexto |

---

## Próximos Pasos Posibles

- Protección por contraseña (descartado por ahora)
- Histórico de precios de plata también en gráfica
- Notificaciones cuando el precio supere un umbral
- Exportar datos a CSV

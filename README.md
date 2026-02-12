# SHO — Gestión de Márgenes de Oro

> **Proyecto de aprendizaje** · Diseñador UX/UI aprendiendo a desarrollar con PHP, SQLite y Claude Code

---

## Qué es esto

Una mini app web interna para una joyería que calcula en tiempo real los márgenes de compra y venta de oro por quilates.

Nada de frameworks. Nada de dependencias. PHP puro, una base de datos en un archivo, y muchas ganas de entender cómo funciona todo por dentro.

## Por qué existe este proyecto

Vengo del diseño. Sé hacer que las cosas se vean bien, pero hasta hace poco el código era territorio ajeno.

Este proyecto nació con dos objetivos:

1. **Resolver un problema real** — calcular márgenes de oro de forma rápida y fiable en el punto de venta
2. **Aprender haciendo** — no con tutoriales genéricos, sino construyendo algo que me importa

La app funciona. Y yo entiendo por qué funciona. Eso es lo que cuenta.

## Stack

| Capa | Tecnología | Por qué |
|---|---|---|
| Frontend | HTML + Tailwind CSS + Chart.js | Sin frameworks, fácil de leer y mantener |
| Backend | PHP 8 puro | Compatible con hosting compartido, sin composer |
| Base de datos | SQLite3 | Un archivo, cero configuración, suficiente para esto |
| Precios | freegoldprice.org API | Precio spot del oro en tiempo real |
| Servidor | Banahosting (hosting compartido) | Sin SSH, sin Docker, solo FTP y cPanel |

## Qué hace

- Muestra el precio BID del oro en EUR/gr en tiempo real
- Calcula márgenes para 5 quilates: 24KT, 21KT, 18KT, 14KT y 9KT
- Muestra el precio de la plata como referencia
- Guarda un historial de precios cada hora (cron job)
- Renderiza una gráfica de evolución de los últimos 7 días
- Incluye calculadora de liquidación por peso y merma

## Cómo correrlo en local

```bash
# Requisito: tener PHP instalado (macOS)
brew install php

# Arrancar servidor local
php -S localhost:8000

# Ejecutar el cron manualmente
php cron.php

# Ver la base de datos
sqlite3 data/prices.db "SELECT * FROM prices ORDER BY recorded_at DESC LIMIT 10;"
```

## Estructura

```
oro-app/
├── index.html      # Toda la UI — precios, gráfica, calculadora
├── bridge.php      # Proxy CORS para obtener el precio del oro
├── db.php          # Conexión SQLite y helpers
├── api.php         # Endpoint JSON con el historial de precios
├── cron.php        # Guarda un precio por hora (ejecutado por cron job)
├── config.php      # ⚠️ Credenciales — no incluido en el repo
└── data/
    └── prices.db   # SQLite — generado automáticamente
```

> `config.php` no está en el repositorio. Contiene la API key y se sube manualmente al servidor.

## Deploy

El proyecto vive en un hosting compartido (Banahosting). Deploy por FTP desde VS Code con la extensión SFTP.

**Cron job en cPanel:**
```
0 * * * * php /home/usuario/superhiperoro.com/oro/cron.php
```

---

## Lo que aprendí construyendo esto

- Cómo estructurar un proyecto PHP sin framework
- Qué es un proxy CORS y por qué existe
- Cómo funciona SQLite y cuándo tiene sentido usarlo
- Por qué las credenciales nunca van en el código
- Que un aviso de PHP `Deprecated` puede romper silenciosamente toda una API JSON
- Cómo configurar un cron job en hosting compartido
- Usar Git, FTP y desplegar a producción desde VS Code

---

*Construido con [Claude Code](https://claude.ai/claude-code) como tutor técnico — haciendo preguntas, cometiendo errores y entendiendo el porqué de cada línea.*

# Universidade Corporativa ISG — Plataforma Moodle Customizada

Tema, blocos e plugin de Trilhas de Aprendizagem construídos em cima do
Moodle 4.5 (tema Boost) pra plataforma de e-learning da Universidade
Corporativa ISG.

## O que tem aqui

Este repositório contém **só o código customizado** — não o Moodle em
si (que é baixado separadamente, veja abaixo).

```
theme_isgcorp/          Tema (sidebar fixa, cores da marca, hero)
local_isgcorp/           Plugin de Trilhas de Aprendizagem
                          (cadastro, upload de imagem, categorias,
                          listagem pública)
blocks/isgcorpdashboard/ Bloco único do painel de Início
                          (hero + continue aprendendo + trilhas
                          recomendadas + estatísticas + progresso +
                          próximos eventos)
docker-compose.yml        Ambiente de desenvolvimento local via Docker
Dockerfile                Imagem PHP + extensões necessárias pro Moodle
```

## Pré-requisitos

- Docker e Docker Compose
- Git

## Como instalar

### 1. Clonar o código-fonte do Moodle

```bash
git clone --branch MOODLE_405_STABLE --depth 1 https://github.com/moodle/moodle.git moodle
```

### 2. Clonar este repositório e copiar os plugins pro lugar certo

```bash
git clone https://github.com/SEU_USUARIO/isg-corporate-university.git isg-custom

cp -r isg-custom/theme_isgcorp moodle/theme/isgcorp
cp -r isg-custom/local_isgcorp moodle/local/isgcorp
cp -r isg-custom/blocks/isgcorpdashboard moodle/blocks/isgcorpdashboard
cp isg-custom/docker-compose.yml .
cp isg-custom/Dockerfile .
```

> **Atenção aos nomes de pasta**: dentro de `theme/`, `local/` e
> `blocks/`, a pasta precisa ter o nome **curto** do plugin (ex:
> `isgcorp`), sem o prefixo `theme_`/`local_`/`block_`. Os comandos
> acima já fazem isso certo.

### 3. Subir o ambiente

```bash
docker compose up -d --build
```

Aguarde alguns minutos (a primeira vez baixa e compila as extensões
do PHP). Depois:

```bash
docker exec -it moodle chown -R www-data:www-data /var/www/moodledata
```

### 4. Instalar pelo navegador

Acesse `http://localhost:8080` e siga o instalador do Moodle. Nos
dados do banco, use:

- **Tipo**: MariaDB (native/mariadb)
- **Host**: `db`
- **Banco**: `moodle`
- **Usuário**: `moodle`
- **Senha**: `moodlepass123`

### 5. Ativar o tema e os plugins

1. **Site administration > Notifications** — instala os plugins
   novos detectados (tema, local_isgcorp, bloco).
2. **Site administration > Appearance > Themes > Theme selector** —
   ativa o tema **ISG Corp**.
3. O bloco **ISG Corp - Painel Início** passa a ser configurado
   automaticamente como dashboard padrão do sistema.
4. O site também passa a exigir login antes de abrir a página
   inicial, evitando que visitantes vejam a home do aluno.

### 6. (Opcional) Certificados

Pra usar o card "Certificados conquistados" no dashboard, instale
também o plugin de terceiros `mod_customcert`
(https://github.com/mdjnelson/moodle-mod_customcert, branch
`MOODLE_404_STABLE` é compatível com Moodle 4.5) na pasta
`mod/customcert`. Sem esse plugin, o card simplesmente não aparece
— não é obrigatório.

## Arquitetura — decisões importantes

- **Sidebar fixa customizada**: não sobrescrevemos os layouts do
  Boost (`drawers.php`), que são complexos. Em vez disso, a sidebar
  é injetada em toda página via `standard_top_of_body_html()` no
  renderer customizado do tema (`theme_isgcorp/classes/output/core_renderer.php`).
- **Painel do Início como bloco único**: a página "My Moodle" empilha
  blocos verticalmente, sem suporte nativo a grade/colunas. Por isso
  todo o conteúdo do Início (hero, cursos, trilhas, stats, progresso,
  eventos) é um bloco só, que desenha o próprio HTML/CSS em grade.
- **Trilhas como plugin próprio**: o Moodle open source não tem o
  conceito de "trilha de aprendizagem" nativamente (isso é uma
  feature paga do Moodle Workplace). `local_isgcorp` implementa isso
  do zero, com tabelas de banco próprias.
- **"Horas de aprendizagem" e "Total de notas"** são calculados a
  partir de dado real do Moodle (logs de acesso e gradebook,
  respectivamente) — não existe "pontuação de gamificação" nativa,
  então não inventamos esse número.

## Cor de marca

`#770104` (vermelho ISG). Configurável em
**Site administration > Appearance > Themes > ISG Corp**.

## Licença

Este código é distribuído sob a mesma licença do Moodle (GNU GPL v3
ou posterior), por ser construído em cima da plataforma Moodle.

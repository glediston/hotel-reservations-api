# Hotel Reservations API

API REST em Laravel para gestão de hotéis, quartos e reservas, desenvolvida para o desafio técnico da Foco Multimídia. Importa dados de arquivos XML via comando agendado (cron), expõe um CRUD de quartos e o cadastro de reservas, tudo com respostas em JSON.

## Tecnologias

- PHP 8.4 e Laravel
- MySQL 8 (Docker)
- Docker e Docker Compose
- PHPUnit (testes automatizados)

## Como rodar

Pré-requisito: Docker Desktop.

```bash
git clone https://github.com/glediston/hotel-reservations-api.git
cd hotel-reservations-api
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan import:xml
```

No PowerShell, troque `cp` por `Copy-Item .env.example .env`.

A API fica em `http://localhost:8000/api`. O MySQL fica exposto na porta `3307` (usuário `hotel`, senha `secret`, banco `hotel_reservas`).

Serviços do `docker-compose.yml`:

| Serviço | Função |
|---|---|
| `app` | Servidor da API (`php artisan serve`) na porta 8000 |
| `scheduler` | Executa o agendador do Laravel (`schedule:work`), que dispara a importação |
| `db` | MySQL 8 |

## Modelagem do banco

O diagrama está em [docs/database/diagram.md](docs/database/diagram.md).

Tabelas: `hotels`, `rooms`, `reserves`, `guests`, `dailies` e `payments`. As tabelas `hotels`, `rooms` e `reserves` têm a coluna `external_id`, que guarda o id vindo do XML. Assim a importação identifica o que já existe sem depender dos ids do banco.

## Importação dos XMLs

Os arquivos ficam em `database/xml/` (`hotels.xml`, `rooms.xml` e `reserves.xml`).

Execução manual:

```bash
docker compose exec app php artisan import:xml
```

Para usar outra pasta: `php artisan import:xml --path=outra/pasta`.

### Execução via cron

O comando está agendado em `routes/console.php` para rodar **todos os dias às 02:00**, sem sobreposição. No Docker, o serviço `scheduler` já executa o agendador continuamente, então não é preciso configurar nada.

Fora do Docker, basta uma linha no cron do servidor:

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Para ver os agendamentos: `docker compose exec app php artisan schedule:list`.

### Regras da importação

- **Idempotente:** pode rodar várias vezes sem duplicar dados. Hotéis, quartos e reservas são atualizados pelo `external_id`. Hóspedes, diárias e pagamentos de cada reserva são recriados a cada execução.
- Cada reserva é gravada em **transação**: ou entra completa, ou não entra.
- Dados inconsistentes não interrompem a importação. Viram aviso no terminal e linha em `storage/logs/laravel.log`:
  - diária com data fora do período da reserva é **ignorada** (ocorre na reserva 6 do XML);
  - soma das diárias diferente do total informado gera aviso (a reserva é importada mesmo assim);
  - reserva cujo quarto não pertence ao hotel informado é ignorada.
- Reservas sem o bloco `<Payments>` (como a reserva 2) são importadas sem pagamentos.

## Endpoints

Todas as respostas são JSON. Erros de validação retornam `422`.

### Quartos (CRUD)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/rooms` | Lista paginada. Filtro opcional: `?hotel_id=1` |
| POST | `/api/rooms` | Cadastra um quarto |
| GET | `/api/rooms/{id}` | Detalha um quarto |
| PUT/PATCH | `/api/rooms/{id}` | Atualiza o nome do quarto |
| DELETE | `/api/rooms/{id}` | Exclui o quarto |

Cadastrar um quarto:

```bash
curl -X POST http://localhost:8000/api/rooms \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"hotel_id": 1, "name": "Suite Master"}'
```

Regras: o nome é único dentro de cada hotel. Só o nome pode ser alterado, porque mudar o hotel de um quarto com reservas deixaria os dados inconsistentes. Excluir um quarto que possui reservas retorna `409`.

### Reservas

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/reserves` | Cria uma reserva |
| GET | `/api/reserves/{id}` | Detalha uma reserva |

Criar uma reserva:

```bash
curl -X POST http://localhost:8000/api/reserves \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "hotel_id": 1,
    "room_id": 1,
    "check_in": "2026-12-20",
    "check_out": "2026-12-23",
    "daily_value": 100,
    "guests": [{"name": "Maria", "last_name": "Souza", "phone": "5571999999999"}],
    "payments": [{"method": 2, "value": 100}]
  }'
```

Resposta (`201`): inclui `total`, `paid`, `balance` e `payment_status`.

Regras de negócio:

- O **total** e as **diárias** são calculados pelo sistema (uma diária por noite, a partir de `daily_value`). O total nunca é informado pelo cliente.
- O quarto precisa pertencer ao hotel informado (`422`).
- **Disponibilidade:** se o quarto já estiver reservado em qualquer parte do período, retorna `409` com a mensagem `Este quarto já está reservado de DD/MM/AAAA a DD/MM/AAAA.` O dia do check-out fica livre para um novo check-in.
- O check-in não pode ser uma data passada e o check-out deve ser posterior ao check-in.
- É obrigatório informar ao menos um hóspede.
- **Pagamentos** são opcionais e podem ser parciais (sinal). O valor pago não pode ultrapassar o total (`422`).
- `payment_status` é calculado: `pendente` (nada pago), `parcial` (pago menos que o total) ou `quitado`.
- A criação usa transação e trava o quarto (`lockForUpdate`), evitando que duas requisições simultâneas reservem o mesmo quarto.
- Valores monetários são calculados em centavos para evitar erros de arredondamento.

### Formas de pagamento

O XML traz apenas o número do método (`Method 1`), sem explicar o significado. **Suposição adotada:** `1 = dinheiro`, `2 = pix`, `3 = cartão`. A tabela está em `Payment::METHODS`.

## Testes

```bash
docker compose exec app php artisan test
```

Os testes usam SQLite em memória (configurado no `phpunit.xml`), então não afetam o banco MySQL de desenvolvimento. Cobertura: CRUD de quartos, importação dos XMLs (idempotência, diária fora do período, reserva sem pagamento) e criação de reservas (cálculo, pagamentos, conflitos de data, validações).

## Organização do código

- `app/Console/Commands/ImportXml.php`: comando de importação
- `app/Services/ReservationService.php`: regras de reserva (disponibilidade, total, pagamentos)
- `app/Http/Controllers/Api/`: controllers enxutos
- `app/Http/Requests/`: validação das entradas
- `app/Http/Resources/`: formato das respostas JSON

## Fluxo de Git

- Uma branch por funcionalidade (`feat/...`, `test/...`, `docs/...`), integrada na `main` por merge.
- Commits no padrão Conventional Commits (`feat`, `fix`, `test`, `docs`, `chore`).
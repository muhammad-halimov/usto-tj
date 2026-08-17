# USTOYOB.TJ API — Reference for Frontend Integration

Base URL: `/api`
Formats: `application/ld+json` (default), `application/json`, `multipart/form-data` (uploads), PATCH uses `application/merge-patch+json`
Auth: JWT Bearer (Lexik) + HttpOnly refresh-token cookie (Gesdinet)
Pagination: page-based, `?page=`, `?itemsPerPage=` (client-controlled where enabled), default 25 / max 50
Locale: `?locale=tj|eng|ru` (default `tj`) — controls localized `title`/`description` fields on translatable entities (Province, District, City, Suburb, Community, Settlement, Village, Category, Occupation, Unit, Legal, AppealReason). The raw `translations` collection is never exposed — the API always returns a flattened, already-localized `title`/`description` string.
Swagger/OpenAPI: enabled at API Platform's default docs entrypoint.

Error format: JSON body `{ "code": "<error_code>", "message": "<localized message>" }` with matching HTTP status. Full catalogue: `GET /api/app-messages` (list) / `GET /api/app-messages/{code}` (single). Message language follows the same `?locale=` param.

---

## 1. AUTH

| Method | Path | Body | Response | Notes |
|---|---|---|---|---|
| POST | `/api/authentication_token` | `{ email, password }` | `{ token, refresh_token_expiration }` | Symfony json_login + Lexik JWT. Sets refresh-token HttpOnly cookie. |
| POST | `/api/refresh_token` | — (cookie-based) | `{ token, refresh_token_expiration }` | Rotates refresh token (single_use: true). |
| POST | `/api/invalidate_token` | — | — | Symfony firewall logout. |
| POST | `/api/logout` | — | `{ success: bool, message }` | App-level logout endpoint (custom). |
| POST | `/api/users` | User registration payload (see §3), validation groups `Default,registration` | User | Public registration. |
| POST | `/api/confirm-account/` | `{ token }` | `{ success, message, redirectUrl }` | Confirms account via emailed token. |
| POST | `/api/confirm-account-tokenless/` | — (Bearer auth) | `{ success, message, redirectUrl }` | Confirms already-authenticated user. |
| POST | `/api/change-password/send-otp/` | `{ email }` | — | Sends OTP code to email. |
| POST | `/api/change-password/` | `{ email, code (6 digits), newPassword }` | — | Password strength: min 8 chars, upper+lower+digit+special. |
| POST | `/api/users/grant-role` | `{ role: "ROLE_MASTER" \| "ROLE_CLIENT" }` | `{ message }` | One-time role grant for a fresh account (errors if already has a role). |

## 2. OAUTH

| Method | Path | Body / Query | Response |
|---|---|---|---|
| GET | `/api/auth/google/url` | — | `{ url }` |
| POST | `/api/auth/google/callback` | `{ code, state, role? }` | `{ user, token, message, status }` |
| GET | `/api/auth/instagram/url` | — | `{ url }` |
| POST | `/api/auth/instagram/callback` | `{ code, state, role? }` | `{ user, token, message, status }` |
| GET | `/api/auth/facebook/url` | — | `{ url }` |
| POST | `/api/auth/facebook/callback` | `{ code, state, role? }` | `{ user, token, message, status }` |
| POST | `/api/auth/telegram/callback` | `{ id, username?, firstName?, lastName?, photoUrl?, role? }` | `{ user, token, message, status }` |
| POST | `/api/profile/oauth/link` | (Bearer) | — |
| DELETE | `/api/profile/oauth/unlink/{provider}` | (Bearer) | — |
| GET | `/api/profile/oauth/providers` | (Bearer) | — |

`provider` values: `google`, `facebook`, `instagram`, `telegram`.

## 3. USERS

| Method | Path | Notes |
|---|---|---|
| GET | `/api/users/social-networks` | → `SocialNetworkOutput[]` `{ id, network }` (distinct networks in use) |
| GET | `/api/users/me` | Full own profile (groups: masters/clients/usersMe/phonesRead) |
| GET | `/api/users/{id}` | Public profile |
| GET | `/api/users` | Collection. Filters: `active`(bool), `atHome`(bool), `rating`(range: `rating[gte]` etc.), `occupation`(exact), `gender`(exact), `socialNetworks`(exact), address filter (see §6), roles filter (`roles[]`), `image` (exists) |
| POST | `/api/users` | Register (see body below) |
| POST | `/api/users/{id}/upload-images` | multipart, field `imageFile[]` |
| PATCH | `/api/users/{id}` | Owner or admin only |
| DELETE | `/api/users/{id}` | Owner or admin only |
| POST | `/api/users/ping` | (Bearer) marks user online (updates `lastSeen`) |
| POST | `/api/users/offline` | (Bearer) marks user offline |

### User entity
```ts
interface User {
  id: number;
  email: string | null;
  login: string | null;
  name: string | null;
  surname: string | null;
  patronymic: string | null;           // masters/clients/tech-support only
  rating: number | null;               // 0–5
  gender: 'gender_female' | 'gender_male' | 'gender_neutral';
  image: string | null;                // filename, build full URL client-side
  imageExternalUrl: string | null;     // e.g. OAuth avatar (read-only)
  description: string | null;
  dateOfBirth: string | null;          // masters/clients/tech-support only, must be 18+
  atHome: boolean | null;
  active: boolean;                     // read-only
  approved: boolean;                   // read-only
  banned: boolean;                     // read-only, admin-only (EasyAdmin), see note below
  lastSeen: string | null;             // ISO datetime, read-only
  isOnline: boolean;                   // computed, read-only (lastSeen within 2 min)
  reviewsCount: number;                // computed, read-only
  roles: string[];                     // read-only, e.g. ["ROLE_MASTER","ROLE_USER"]
  socialNetworks: SocialNetwork[];
  education: Education[];
  occupation: Occupation[];            // master's occupations/subcategories
  addresses: Address[];
  phones: Phone[];                     // read groups: phonesRead; write groups: phonesWrite
  oauthProviders: OAuthProvider[];     // only on /users/me
  createdAt: string;
  updatedAt: string | null;
}

interface Phone {
  id: number;
  phone: string;          // normalized, e.g. "+992xxxxxxxxx"
  countryCode: string;    // e.g. "+992"
  main: boolean;
  verified: boolean;      // read-only
}

interface Education {
  id: number;
  title: string | null;
  description: string | null;
  beginning: number | null;   // year
  ending: number | null;      // year
  graduated: boolean | null;
  occupation: Occupation | null;
}

interface SocialNetwork {
  id: number;
  network: string;   // instagram|telegram|whatsapp|facebook|vk|youtube|site|viber|imo|twitter|linkedin|google|wechat
  handle: string | null;
}

interface OAuthProvider {
  id: number;
  provider: 'google' | 'facebook' | 'instagram' | 'telegram';
  providerId: string;
}
```
`User.ROLES` = `{ ROLE_ADMIN, ROLE_MASTER, ROLE_CLIENT }` (+ implicit `ROLE_USER`). There's also `ROLE_SUPER_ADMIN` (not in this map — assigned outside the normal role-grant flow): it's a superset of `ROLE_ADMIN` — a `ROLE_SUPER_ADMIN` account satisfies every "`ROLE_ADMIN` only" check documented below (`roles` in `GET /users/*` responses will show both, e.g. `["ROLE_SUPER_ADMIN","ROLE_ADMIN","ROLE_USER"]`) and additionally bypasses `active`/`approved` gating entirely.
`User.GENDERS` = `{ gender_female, gender_male, gender_neutral }`.
`Phone.CODES`: `+992, +998, +996, +7, +1, +44, +49, +33, +86, +81, +91, +971, +380, +375`.

Registration body (`POST /api/users`): standard User writable fields — `email`, `password`, `name`, `surname`, `patronymic?`, `gender?`, `dateOfBirth?`, phones etc. (validation groups `registration`).

`User.banned` — same mechanism as `Ticket.banned` (§4): never writable via the API, toggled only from EasyAdmin by ROLE_SUPER_ADMIN. Setting it forces `active=false`/`approved=false` immediately, and while `true` neither can be set back to `true` through any code path — including self-activation via `POST /confirm-account/` (token) and `POST /confirm-account-tokenless/` (resend). On top of the entity-level guard, `AccessService::check()` now hard-rejects a banned user (`403 access_denied`) on **every** authenticated endpoint that goes through `checkedUser()` — unconditionally, regardless of the `activeAndApproved` flag some endpoints pass `false` for (that flag only relaxes the "email not confirmed yet" gate, not a ban). `ROLE_SUPER_ADMIN` still bypasses everything, same as it already did for active/approved.

## 4. TICKETS (services/listings)

| Method | Path | Notes |
|---|---|---|
| GET | `/api/tickets/me` | Own tickets (as author or master), regardless of `approved` |
| GET | `/api/tickets/{id}` | Public single |
| GET | `/api/tickets` | Public collection. Filters: `active`,`service`,`negotiableBudget` (bool); `master`,`author` (exists); `category`,`subcategory`,`master`,`author` (exact), `description` (partial); address filter (§6); range filters `budget`,`master.rating`,`author.rating`,`reviewsCount` |
| POST | `/api/tickets` | body: `TicketInput` |
| POST | `/api/tickets/{id}/upload-images` | multipart `imageFile[]`. `403 access_denied` if `banned` |
| PATCH | `/api/tickets/{id}` | body: `TicketPatchInput`. `403 access_denied` if `banned` — blocks the *entire* request before any field is touched, not just specific fields |

```ts
interface TicketInput {
  title?: string;
  description?: string;
  notice?: string;
  active?: boolean;
  budget?: number;
  negotiableBudget?: boolean;
  priority?: number;      // no fixed enum server-side (unlike TechSupport.PRIORITIES) — plain nullable int
  category?: string;      // IRI e.g. "/api/categories/1"
  subcategory?: string;   // IRI to Occupation
  unit?: string;          // IRI to Unit
  address: object[];      // raw address components, see AddressFilter/§6
}
interface TicketPatchInput extends TicketInput {
  images?: { image: string }[];   // reorder/prune existing MultipleImage refs by filename
}

interface Ticket {
  id: number;
  notice: string | null;
  budget: number | null;
  negotiableBudget: boolean | null;
  priority: number | null;
  service: boolean | null;         // true = "service offer", false = "listing/request"
  active: boolean | null;
  approved: boolean;               // read-only, admin-gated visibility
  banned: boolean;                 // read-only, admin-only (EasyAdmin), see note below
  viewsCount: number;
  responsesCount: number;
  reviewsCount: number;
  title: string | null;
  description: string | null;
  category: Category | null;
  subcategory: Occupation | null;
  unit: Unit | null;
  author: User | null;
  master: User | null;
  addresses: Address[];
  images: MultipleImage[];
  createdAt: string;
  updatedAt: string | null;
}
```

`Ticket.approved` is also reset to `false` automatically whenever a content-affecting field changes (`title`, `description`, `notice`, `budget`, `negotiableBudget`, `service`, `active`, `priority`, `category`, `subcategory`, `unit`) — same field list `TicketListener` already used for the admin re-review notification. A previously-approved ticket drops out of public `/tickets` listings again until an admin re-approves. See §15 for the audit trail this now writes.

`Ticket.banned` — not present in `TicketInput`/`TicketPatchInput` at all (never writable via the API, `#[ApiProperty(writable: false)]`), toggled only from EasyAdmin by ROLE_SUPER_ADMIN. While `true`: `PATCH /tickets/{id}` rejects the whole request (`403 access_denied`) before touching *any* field — including otherwise author-writable ones like `active` — and `POST /tickets/{id}/upload-images` rejects the same way for non-admins. Same enforcement point (`ApiPostUniversalImageController::performAdditionalChecks`) also drives the analogous `TechSupport.STATUS_BANNED` lock. Entity-level invariant (enforced in `Ticket::setBanned/setActive/setApproved`, not just at the controller): setting `banned=true` immediately forces `active=false` and `approved=false` (which also drops the ticket out of public `/tickets` listings, since `approved=false` already gates visibility), and while `banned=true` neither field can be flipped back to `true` through any code path — including the `TicketApproval::setApproved(true)` cascade.

### Category / Unit / Occupation (catalogue, read-only for frontend)
```
GET /api/categories        GET /api/categories/{id}   filters: occupations, title, description(partial)
GET /api/units              GET /api/units/{id}
GET /api/occupations        GET /api/occupations/{id}
```
```ts
interface Category { id: number; title: string; description: string|null; image: string|null; priority: number|null; occupations: Occupation[]; }
interface Unit     { id: number; title: string; description: string|null; priority: number|null; }
interface Occupation { id: number; title: string; description: string|null; image: string|null; priority: number|null; categories: Category[]; }
```

## 5. CHATS

| Method | Path | Notes |
|---|---|---|
| GET | `/api/chats/{id}` | |
| GET | `/api/chats/{id}/subscribe` | Returns Mercure subscriber JWT for this chat's SSE topic (`chat:{id}`) |
| GET | `/api/chats/inbox-token` | Mercure token covering ALL of the caller's chats (global unread badge) |
| GET | `/api/chats/me` | Query: `?ticket=<id>` `?active=true|false` |
| POST | `/api/chats` | body: `ChatPostInput` |
| POST | `/api/chats/{id}/read` | marks messages read, no body |
| PATCH | `/api/chats/{id}` | body: `ChatPatchInput` |
| DELETE | `/api/chats/{id}` | "Delete for me" — see below, not a hard delete |

| Method | Path | Notes |
|---|---|---|
| GET | `/api/chat-messages/{id}` | |
| POST | `/api/chat-messages` | body: `ChatMessagePostInput` |
| POST | `/api/chat-messages/{id}/upload-images` | multipart `imageFile[]` |
| PATCH | `/api/chat-messages/{id}` | body: `ChatMessagePatchInput` |
| DELETE | `/api/chat-messages/{id}` | |

```ts
interface ChatPostInput  { replyAuthor?: string /* User IRI */; ticket?: string /* Ticket IRI */; }
interface ChatPatchInput { active: boolean; }
interface ChatMessagePostInput  { chat: string /* Chat IRI, required */; description: string; replyTo?: string /* ChatMessage IRI */; }
interface ChatMessagePatchInput { chat?: string; description?: string; images?: { image: string }[]; }

interface Chat {
  id: number;
  active: boolean | null;
  author: User | null;        // read-only (set from bearer on creation)
  replyAuthor: User | null;
  ticket: Ticket | null;
  messages: ChatMessage[];    // read-only
  images: MultipleImage[];    // computed: aggregated from all messages, newest first, read-only
  mercureTopic: string;       // "chat:{id}" — subscribe via Mercure hub using the token from /subscribe
  hiddenByAuthor: boolean;      // read-only, see "Delete for me" below
  hiddenByReplyAuthor: boolean; // read-only, see "Delete for me" below
  createdAt: string;
  updatedAt: string | null;
}

interface ChatMessage {
  id: number;
  chat: Chat | null;
  author: User | null;        // read-only
  replyTo: ChatMessage | null;
  readAt: string | null;      // read-only
  description: string | null;
  images: MultipleImage[];
  createdAt: string;
  updatedAt: string | null;
}
```
Real-time: Mercure. Fetch a subscribe token (`/chats/{id}/subscribe` or `/chats/inbox-token`), then open an EventSource against the Mercure hub URL with that JWT, subscribed to topic `chat:{id}` (per-chat) — hub URL is not part of this API's JSON, get it from app config/`.well-known/mercure` discovery.

**"Delete for me"** (`DELETE /api/chats/{id}`): not a hard delete for either party by itself. It sets the caller's own `hiddenByAuthor`/`hiddenByReplyAuthor` flag (whichever one matches which side of the chat they're on) and always returns `204`. A chat hidden by the caller stops showing up in **that caller's own** `GET /api/chats/me` — the other participant still sees it normally, and `GET /api/chats/{id}` by direct id still works for both sides regardless of either flag (hiding only affects the list, not direct access). Once **both** flags are `true` (both sides called `DELETE`), the chat — and its messages/photos — are actually deleted, in that same request that flips the second flag. Calling `DELETE` again after already hiding it is a no-op (still `204`).

## 6. GEOGRAPHY

```
GET /api/provinces          GET /api/provinces/{id}
GET /api/districts          GET /api/districts/{id}    filters: province.id, communities.id, settlements.id, settlements.villages.id (exact); title/description/province.title/communities.title/settlements.title/settlements.villages.title (partial, translation-aware)
GET /api/cities             GET /api/cities/{id}       filters: province.id, suburbs.id (exact); title/description/province.title/suburbs.title (partial)
```
Suburb, Community, Settlement, Village have **no direct endpoints** — they only appear nested inside District/City/Settlement responses.

```ts
interface Province { id: number; title: string; description: string|null; image: string|null; cities: City[]; districts: District[]; }
interface District { id: number; title: string; description: string|null; image: string|null; province: Province; settlements: Settlement[]; communities: Community[]; }
interface City      { id: number; title: string; description: string|null; image: string|null; province: Province; suburbs: Suburb[]; }
interface Suburb     { id: number; title: string; description: string|null; image: string|null; }
interface Community  { id: number; title: string; description: string|null; image: string|null; }
interface Settlement { id: number; title: string; description: string|null; image: string|null; villages: Village[]; }
interface Village    { id: number; title: string; description: string|null; image: string|null; }
```
`Province.PROVINCES` (labels): Душанбе, ГРРП, ГБАО, Согдийская область, Хатлонская область.

**Address** (embedded, no own endpoint — attached to User/Ticket via `addresses[]`, written as raw component IRIs/objects in `TicketInput.address` / User payload):
```ts
interface Address {
  id: number;
  province: Province | null;
  city: City | null;
  suburb: Suburb | null;
  district: District | null;
  settlement: Settlement | null;
  community: Community | null;
  village: Village | null;
}
```
`AddressFilter` on `/api/users` and `/api/tickets`: filter by nested geography, e.g. `?address.province=<id>`, `?address.city=<id>`, etc. — inspect `AddressFilter` at request time if exact param names are needed; the frontend agent should confirm via a live OPTIONS/GET call.

## 7. GALLERIES

| Method | Path | Notes |
|---|---|---|
| GET | `/api/galleries/me` | |
| GET | `/api/galleries` | filter: `user` (exact) |
| POST | `/api/galleries` | no body — creates empty gallery for bearer |
| POST | `/api/galleries/{id}/upload-images` | multipart `imageFile[]` |
| PATCH | `/api/galleries/{id}` | body: `GalleryPatchInput` |
| DELETE | `/api/galleries/{id}` | owner or admin |

```ts
interface GalleryPatchInput { images?: { image: string }[]; }
interface Gallery { id: number; user: User; images: MultipleImage[]; createdAt: string; updatedAt: string|null; }
```

## 8. REVIEWS

| Method | Path | Notes |
|---|---|---|
| GET | `/api/reviews/me` | |
| GET | `/api/reviews/{id}` | |
| GET | `/api/reviews` | Filters: `ticket.service`(bool); `ticket`,`master`,`client`,`images`(exists); `type`,`master`,`client`,`ticket`,`ticket.title`(exact/partial); `rating`(range) |
| POST | `/api/reviews/{id}/upload-images` | multipart `imageFile[]` |
| POST | `/api/reviews` | body: `ReviewPostInput` |
| PATCH | `/api/reviews/{id}` | body: `ReviewPatchInput` |
| DELETE | `/api/reviews/{id}` | owner-side only (client reviewing master or vice-versa) |

```ts
interface ReviewPostInput  { type?: 'client'|'master'; rating: number; ticket?: string /* IRI */; description?: string; master?: string /* IRI */; client?: string /* IRI */; }
interface ReviewPatchInput { rating: number; description?: string; images: { image: string }[]; }

interface Review {
  id: number;
  rating: number | null;         // 0–5
  type: 'client' | 'master';     // "Отзыв клиенту" | "Отзыв мастеру"
  title: string | null;
  description: string | null;
  ticket: Ticket | null;
  master: User | null;
  client: User | null;
  images: MultipleImage[];
  createdAt: string;
  updatedAt: string | null;
}
```

## 9. APPEALS (жалобы/complaints)

| Method | Path | Notes |
|---|---|---|
| GET | `/api/appeals/me` | own appeals (as author) |
| GET | `/api/appeals/{id}` | **ROLE_ADMIN only** |
| GET | `/api/appeals` | **ROLE_ADMIN only**. Filters: `type`,`title`(partial),`description`(partial),`reason`,`author`,`respondent` |
| POST | `/api/appeals` | body: `AppealInput` |
| POST | `/api/appeals/{id}/upload-images` | multipart `imageFile[]` |

```ts
interface AppealInput {
  type?: 'ticket' | 'chat' | 'review' | 'user';
  title?: string;
  description?: string;
  reason?: string;       // AppealReason IRI
  respondent?: string;   // User IRI
  ticket?: string;       // Ticket IRI (also required alongside chat/review depending on type)
  chat?: string;         // Chat IRI (type=chat)
  review?: string;       // Review IRI (type=review)
}

interface Appeal {   // base shape; subtype adds `chat` or `review`
  id: number;
  title: string | null;
  description: string | null;
  type: 'ticket' | 'chat' | 'review' | 'user';
  reason: AppealReason | null;
  author: User | null;
  respondent: User | null;
  ticket: Ticket | null;
  chat?: Chat | null;      // present only for type=chat (AppealChat)
  review?: Review | null;  // present only for type=review (AppealReview)
  images: MultipleImage[];
  createdAt: string;
  updatedAt: string | null;
}
```
`Appeal.TYPES` labels: Услуга/Объявление→ticket, Чат→chat, Отзыв→review, Пользователь→user. Concrete subclasses: `AppealChat`, `AppealReview`, `AppealTicket`, `AppealUser` (single-table-ish via `type` discriminator, all served through the same `/api/appeals*` endpoints).

```
GET /api/appeal-reasons          GET /api/appeal-reasons/{id}
```
filters: `applicableTo`(exact), `authRequired`(bool)
```ts
interface AppealReason {
  id: number;
  code: string;
  title: string;                 // localized
  applicableTo: 'chat'|'ticket'|'review'|'user'|'support'|'overall';
  authRequired: boolean;
}
```

## 10. FAVORITES & BLACKLIST

| Method | Path | Notes |
|---|---|---|
| GET | `/api/favorites/me` | filter: `type` (CollectionEntryTypeFilter — likely `?type=user` or `?type=ticket`) |
| POST | `/api/favorites` | body: `{ user?: IRI, ticket?: IRI }` (exactly one) |
| DELETE | `/api/favorites/{id}` | owner or admin |
| GET | `/api/black-lists/me` | no filters — every entry is a user-block, nothing else |
| POST | `/api/black-lists` | body: `{ user: IRI }` — blocks that user in chat |
| DELETE | `/api/black-lists/{id}` | owner or admin — **unblocks** |

Self-referencing is allowed: a user can favorite their own `user` IRI or their own `ticket` IRI — no ownership check blocks it server-side. `409 already_added` means this exact `(owner, target)` pair already exists for the caller, not a permission error.

```ts
interface CollectionEntryInput { user?: string; ticket?: string; }  // IRIs, mutually exclusive
interface Favorite  { id: number; type: string; user: User|null; ticket: Ticket|null; createdAt: string; updatedAt: string|null; }

interface BlackListInput { user: string; }  // IRI, required — no ticket option anymore
interface BlackList { id: number; user: User; createdAt: string; updatedAt: string|null; }  // no `type`, no `ticket` — a block is always exactly one user
```
`GET /api/favorites/me` additionally hides entries whose target `ticket` isn't `approved` yet, or whose target `user` isn't `active`+`approved` — filtered at the query level, so pagination/`totalItems` reflect only what's actually visible.

**BlackList semantics (redesigned)** — blocking is chat-only and asymmetric:
- `POST /black-lists { user: IRI }` blocks that user from messaging you. There's no `ticket` variant anymore (removed).
- Only the **blocked** user is restricted — the one who created the block can still send messages if they want; the restriction targets the block's target, not both sides (previously it blocked both directions).
- Restriction covers exactly two things: `POST /chats` (starting a new conversation) and `POST /chat-messages` (sending in an existing one, `403 user_blocked`) — plus attaching photos to your own chat message via `upload-images`. Nothing else is gated: reviews, tickets, favoriting, profile viewing all remain fully available to the blocked user, in both directions.
- Existing chat history is never touched — messages already sent stay visible and unmodified to both parties; the block only prevents *new* writes from the blocked side.
- `PATCH /chats/{id}` (toggling `active`) is unaffected by blocking — that's not "writing" in this sense.
- `DELETE /black-lists/{id}` unblocks immediately.

## 11. TECH SUPPORT

| Method | Path | Notes |
|---|---|---|
| GET | `/api/tech-supports` | ROLE_ADMIN: all tickets |
| GET | `/api/tech-supports/me` | any authed user: own (as author or administrant) |
| GET | `/api/tech-supports/user/{id}` | ROLE_ADMIN: by user |
| GET | `/api/tech-supports/admin/{id}` | ROLE_ADMIN: by assigned admin |
| GET | `/api/tech-supports/{id}` | author/administrant/admin |
| GET | `/api/tech-supports/{id}/subscribe` | Returns Mercure subscriber JWT for this ticket's SSE topic (`tech-support:{id}`). **Only** the ticket's author or its assigned `administrant` can call this — unlike the plain GET above, a generic ROLE_ADMIN without an assignment is rejected (`403 ownership_mismatch`). This is a private 1:1 channel, not an admin monitoring tool. |
| GET | `/api/tech-supports/inbox-token` | Mercure token covering ALL of the caller's tickets at once (as author or administrant — same scope as `/tech-supports/me`). Empty set → `{ token: null, topics: [] }` |
| POST | `/api/tech-supports` | body: `TechSupportPostInput`. Works for guests too (no Bearer) — then `guestEmail` is required |
| POST | `/api/tech-supports/{id}/read` | Marks all unread messages of this ticket as read for the caller (sets `readAt`). "Unread" = `author != caller` and `readAt` still `null`. Access: author, assigned `administrant`, **or any `ROLE_ADMIN`** (broader than `/subscribe`, which stays author+assigned-only). `403 ownership_mismatch` otherwise. No body, `204 No Content`. |
| PATCH | `/api/tech-supports/{id}` | body: `TechSupportPatchInput`. **Author**: `status` only (state machine below). **Admin**: `status` + `title`/`reason`/`priority`/`description`/`images` — all silently no-op for non-admins (200, field just doesn't change), no error thrown. |
| PATCH | `/api/tech-supports/{id}/assign` | ROLE_ADMIN, body: `TechSupportAssignInput` |
| POST | `/api/tech-supports/{id}/upload-images` | multipart `imageFile[]` |

```ts
// Общая база для POST и PATCH — тот же паттерн, что TicketInput → TicketPatchInput.
interface TechSupportInput { title?: string; reason?: string /* AppealReason IRI */; priority?: string; description?: string; }
interface TechSupportPostInput extends TechSupportInput { guestEmail?: string; }
interface TechSupportPatchInput extends TechSupportInput {
  status?: 'new'|'renewed'|'in_progress'|'resolved'|'closed'|'banned';
  images?: { image: string }[];   // admin-only; reorder/prune existing MultipleImage refs by filename, same syncImages() mechanism as Chat/Ticket
}
interface TechSupportAssignInput { administrant: string /* User IRI */; }

// Deliberately trimmed shape — only these 6 fields are ever exposed for
// `administrant`, everywhere it appears (TechSupport.administrant). No `id`.
interface AdministrantPublic {
  name: string | null;
  surname: string | null;
  patronymic: string | null;
  lastSeen: string | null;
  image: string | null;
  imageExternalUrl: string | null;
}

interface TechSupport {
  id: number;
  title: string | null;
  description: string | null;
  priority: number | null;              // 1 low .. 4 urgent
  reason: AppealReason | null;
  status: string;                       // read-only, see STATUSES
  administrant: AdministrantPublic | null; // read-only, trimmed shape (see above) — NOT a full User
  author: User | null;                  // read-only, full User shape (unaffected)
  guestEmail: string | null;            // only set for guest-created tickets
  guestAccessToken: string | null;      // returned ONLY in the POST response, lets a guest upload images afterwards
  images: MultipleImage[];
  messages: TechSupportMessage[];
  mercureTopic: string;                 // "tech-support:{id}" — subscribe via Mercure hub using the token from /subscribe
  createdAt: string;
  updatedAt: string | null;
}
```
`TechSupport.STATUSES`: new, renewed, in_progress, resolved, closed, **banned**.
`TechSupport.PRIORITIES`: 1=Низкий, 2=Средний, 3=Высокий, 4=Экстренный.

**Status transitions on `PATCH /tech-supports/{id}`**: `ROLE_ADMIN` (incl. `ROLE_SUPER_ADMIN`) can set `status` to *any* value from *any* current value — no restrictions, including out of `banned` (unban). The **author** can only self-transition `resolved → renewed` or `closed → renewed` (reopen — e.g. the ticket was closed with no response, and the client comes back); every other status change by a non-admin returns `403 extra_denied`.

**`banned`** — reachable only by `ROLE_ADMIN`, from any status (including `closed`). While a ticket is `banned`, the **author loses all interaction**: `POST /tech-support-messages` and `POST /tech-supports/{id}/upload-images` both return `403 access_denied` for the author/guest — only `ROLE_ADMIN` can still post messages or images on a banned ticket. Unlike before, admin CAN move it back out of `banned` via `PATCH .../{id}`.

Real-time: Mercure, same mechanism as Chat (§5). Fetch a subscribe token (`/tech-supports/{id}/subscribe` or `/tech-supports/inbox-token`), then open an EventSource against the Mercure hub URL with that JWT, subscribed to topic `tech-support:{id}`. Two event types on this topic:
- `{"type":"created","data":{...TechSupportMessage...}}` — new message posted. Editing/deleting a `TechSupportMessage` does *not* emit an event; poll GET for that.
- `{"type":"updated","data":{...TechSupport...}}` — status changed via `PATCH /tech-supports/{id}` (by either admin or author's self-transitions). Only fires on an actual status change, not on other field edits (title/description/priority/reason/images PATCHed alone do *not* emit an event). `data` shape matches the normal `TechSupport` GET response.

| Method | Path | Notes |
|---|---|---|
| GET | `/api/tech-support-messages/{id}` | |
| POST | `/api/tech-support-messages` | body: `TechSupportMessagePostInput` |
| PATCH | `/api/tech-support-messages/{id}` | body: `TechSupportMessagePatchInput` |
| DELETE | `/api/tech-support-messages/{id}` | |
| POST | `/api/tech-support-messages/{id}/upload-images` | multipart `imageFile[]` |

Photo-only messages (no text): send `description: ""` on `POST /tech-support-messages`, then attach the photo via the separate `upload-images` call — same two-step pattern as `ChatMessage`. Only an actually-*missing* `description` (`null`/omitted) is rejected (`400 empty_text`); an empty string passes through.

```ts
interface TechSupportMessagePostInput  { techSupport?: string /* IRI */; description?: string; }
interface TechSupportMessagePatchInput { description?: string; images?: { image: string }[]; }  // images: same syncImages() mechanism as ChatMessagePatchInput — reorder/prune by filename, either field alone is enough (400 nothing_to_update if both omitted)
interface TechSupportMessage {
  id: number;
  author: User|null;
  techSupport: TechSupport|null;
  description: string|null;
  readAt: string | null;   // ISO datetime, read-only — set via POST /tech-supports/{id}/read
  images: MultipleImage[];
  createdAt: string;
  updatedAt: string|null;
}
```
Note: marking read does **not** emit a Mercure event (unlike Chat, where `/chats/{id}/read` triggers an `"updated"` SSE so the sender sees a second checkmark live) — `TechSupportMessageListener` only publishes on message creation. Poll/re-fetch to see updated `readAt`.

## 12. LEGAL

```
GET /api/legals        GET /api/legals/{id}
```
filters: `type`(exact), `title`, `description`(partial)
```ts
interface Legal { id: number; type: 'terms_of_use'|'privacy_policy'|'public_offer'; title: string; description: string|null; }
```

## 13. APP MESSAGES (error/message catalogue)

```
GET /api/app-messages          → { code, message, http }[]
GET /api/app-messages/{code}   → { code, message, http }
```
Use to map backend error `code` → localized display text without hardcoding strings client-side; respects `?locale=`.

## 14. SHARED / CROSS-CUTTING TYPES

```ts
interface MultipleImage {
  id: number;
  author: User | null;
  image: string;        // filename — build full URL via configured storage base path
  priority: number | null;
  createdAt: string;
  updatedAt: string | null;
}
```
Universal image upload pattern — every resource that has images exposes:
`POST /api/{resource}/{id}/upload-images` — `multipart/form-data`, field `imageFile[]` (multiple files, each ≤10MB, png/jpeg/jpg/webp) → `{ message: string, count: number }`.
Reordering/removing already-uploaded images on PATCH: pass `images: [{ image: "<filename>" }, ...]` in entity's Patch DTO (Ticket, Review, Chat message, Gallery, Tech support ticket [admin-only], Tech support message) — order defines new `priority`; filenames omitted from the array are detached.

Admin photo moderation — `DELETE /api/multiple-images/{id}` — **ROLE_ADMIN/ROLE_SUPER_ADMIN only**, no ownership check at all (unlike the PATCH-based removal above, which only the entity's own author/participant can do). Deletes by the photo's own id regardless of which entity owns it — a client-side "which resource is this on" lookup isn't needed. Logs the same `EntityRevision` (`entityType: "multiple_image"`, `action: "deleted"`) as PATCH-triggered removal, so moderation deletions show up in the same audit trail either way.

## 15. AUDIT TRAIL (entity revisions)

| Method | Path | Notes |
|---|---|---|
| GET | `/api/entity-revisions` | **ROLE_ADMIN only**. Filters: `entityType`, `entityId`, `parentId`, `entity`, `action` (all exact) |
| GET | `/api/entity-revisions/{id}` | **ROLE_ADMIN only** |

No `POST`/`PATCH`/`DELETE` — rows are written only by server-side listeners, never through the API, and physically cannot be modified/removed via it (`405` on both).

```ts
interface EntityRevision {
  id: number;
  entityType: string;              // e.g. "ticket"
  entityId: number;
  parentId: number | null;         // id of the entity this one nests under — see below; null if there isn't one
  entity: string | null;           // short class name of whatever parentId refers to — see below
  action: 'updated' | 'deleted';
  snapshot: Record<string, unknown>;  // shape depends on entityType/action — see per-entity notes below
  actor: User | null;              // who made the change; null if the account was since deleted
  actorLabel: string | null;       // actor's email, snapshotted at write time — survives account deletion (actor doesn't: FK is ON DELETE SET NULL)
  actorId: number | null;          // actor's id, same snapshot (plain column, no FK — survives deletion same as actorLabel)
  actorName: string | null;        // actor's first name, same snapshot
  actorSurname: string | null;     // actor's last name, same snapshot
  reason: string | null;           // optional, mostly for moderator deletions
  expiresAt: string | null;        // when this row becomes eligible for deletion; null = kept forever
  createdAt: string;
}
```

**`parentId`**: the id of whatever directly owns `entityId` (the immediate FK, not the topmost ancestor) — lets you find every revision nested under one object even when they span different `entityType`s. Per `entityType`: `ticket` → always `null` (root of the hierarchy); `chat_message` → the id of its `Chat` (`ChatMessage.chat`, not the chat's `Ticket`); `tech_support_message` → the id of its `TechSupport`; `review` → the id of its `Ticket`; `multiple_image` → the id of whichever entity owned the deleted photo.

**`entity`**: the short class name of whatever `parentId` points to (`"Ticket"`, `"Chat"`, `"TechSupport"`, `"Review"`, `"ChatMessage"`, `"TechSupportMessage"`, `"Gallery"`, `"Appeal"`) — or, for `entityType: "ticket"` (no parent), the class name of the row itself (`"Ticket"`). Lets you filter/group without having to know which `entityType`s nest under which parent. Admin-panel translation table: `EntityRevision::ENTITIES` (`App\Entity\Extra\EntityRevision`).

**Retention**: every revision defaults to `expiresAt = createdAt + 14 days`. A writer can pass `null` instead to keep a specific row forever — nothing currently does, but the field/mechanism supports it. Expiry is not automatic/DB-enforced — actual deletion only happens when `php bin/console app:prune-entity-revisions` runs (cron, not wired up by default in this repo). `expiresAt` is read-only from the API regardless (no write operations on this resource at all).

`entityType` values currently written, and what triggers each. For `action: "updated"`, `snapshot[field]` is always `{ old: <previous value>, new: <value after the edit> }` — both sides included, not just the previous one:

- **`ticket`** — on every `PATCH /tickets/{id}` that changes at least one of `title`/`description`/`notice`/`budget`/`negotiableBudget`/`service`/`active`/`priority`/`category`/`subcategory`/`unit`. `snapshot` contains only the fields that actually changed (association fields like `category`/`subcategory`/`unit` are stored as their id, not the embedded object, on both `old` and `new`). Same trigger also resets `Ticket.approved` to `false` — see §4.
- **`chat_message`** — on `PATCH /chat-messages/{id}` that changes `description`. `{ action: "updated", snapshot: { description: { old: "...", new: "..." } } }`.
- **`tech_support_message`** — on `PATCH /tech-support-messages/{id}` that changes `description`. Same shape as `chat_message`.
- **`review`** — on `PATCH /reviews/{id}` that changes `description` and/or `rating`. `snapshot` contains only whichever of the two actually changed, each as an `{ old, new }` pair.
- **`multiple_image`** — not an old/new edit-snapshot like the above (deleted photos have no "new" value); `{ action: "deleted" }` whenever one or more existing photos are dropped from any entity's `images` array on PATCH (Ticket/Review/Chat message/Tech support/Tech support message/Gallery/Appeal — anything with `HasImagesInterface`, all funnel through the same `syncImages()` helper), or via `DELETE /api/multiple-images/{id}` (admin moderation, see above). **One row per batch, not per photo** — if a single PATCH drops 3 photos at once, that's one `EntityRevision` listing all 3, not three separate rows. `entityId` is the *first* deleted photo's own id (there's no single id once it's a batch — the full list is in `snapshot`). `snapshot: { images: [{ image: "<full path, e.g. /uploads/tickets/abc123.png>" }, ...] }` — full path (`uri_prefix` + directory + filename, matching what `EntityDirectoryNamerService` actually resolves it to), not just the bare filename.

`reason` is writable from the admin panel only (not set by any listener, not writable via this API at all) — a free-text note an admin can attach after the fact, e.g. to explain a moderator-triggered deletion.

Note: for `chat_message`/`tech_support_message`/`review`, editing is currently allowed by *any* party of the parent relationship (both chat participants, or either review side), not strictly the original message/review author — see the ownership checks on the respective `PATCH` endpoints. `actor` on the revision reflects whoever actually made the request, which may not be the original author.

Every entity uses IRIs (`/api/resource/{id}`) for relations in write payloads (standard API Platform / Hydra convention), and returns embedded objects (or IRIs, depending on `normalizationContext`) on read.

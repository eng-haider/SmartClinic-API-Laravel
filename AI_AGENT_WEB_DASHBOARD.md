# AI Action Agent — Web Dashboard (SmartClinic-Front)

How the web dashboard's chatbot works with the action agent: the API it calls, how each reply is shown, the rules the UI must keep, and what is still to do.

Repo: `SmartClinic-Front` (Vue 3 + Vuetify). Backend reference: `AI_AGENT_DOCUMENTATION.md` in the API repo.

**It is the same chatbot.** The floating chat widget (`AiChatWidget.vue`) and the same endpoint (`POST /ai/chat`) handle questions and actions; there is no separate agent page, service or API.

## What the feature does

Staff type a request in the chat ("احجزلي موعد لأحمد باچر الساعة 5", "سجل تسديد 50 ألف", "Book Ahmed for tomorrow at 5 PM"). The agent:

1. asks for anything missing or unclear (which patient, which day, "50,000 or 50?"),
2. shows a **confirmation card** with the exact details,
3. performs the operation through the clinic API **only when the user presses Confirm** (or replies a plain "نعم" / "yes"),
4. shows the real result returned by the API.

The UI never decides anything. It shows what the server returns and sends back what the user presses.

## Status in this repo

| File | State |
|---|---|
| `src/services/ai.service.js` | **Done.** One `chat()` method: `chat('text')` is a one-shot answer (the patient analysis drawer, unchanged); `chat({ message, … })` is the chat widget with actions. Plus `confirmAction`, `cancelAction` and `getAction` on `/ai/chat/actions/*`. `analyzeXray` is unchanged. |
| `src/components/AiChatWidget.vue` | **Done.** Text messages go to the agent. It renders badges, fields, lists, notes, confirmation buttons and option chips, with RTL/LTR per message. The X-ray image path is unchanged. |
| `src/views/patients/PatientDetail.vue` | **Unchanged.** Its AI analysis calls `aiService.chat(prompt)` with a string, which sends `answer_only` and gets the same one-shot answer as before (no actions). |

Not verified yet: the widget compiles, but it has not been clicked through in a browser. Run the QA checklist at the end before release.

## API

All paths go through `src/services/api.js`, which adds `/tenant`, the JWT, `X-Tenant-ID` and `X-Clinic-ID`. The base is `/api/tenant/ai/chat`.

| Call | Method & path | Body |
|---|---|---|
| Send a message | `POST /ai/chat` | `{ message, conversation_id? \| new_conversation: true, client_message_id }` |
| Press an option chip | `POST /ai/chat` | `{ option_id, conversation_id }` |
| Confirm a card | `POST /ai/chat/actions/{id}/confirm` | `{ conversation_id }` |
| Cancel a card | `POST /ai/chat/actions/{id}/cancel` | `{ conversation_id }` |
| Action status (after a timeout) | `GET /ai/chat/actions/{id}` | — |
| Conversation history | `GET /ai/chat/conversations/{id}` | — |

`conversation_id` is returned by the first message. Send it with every later call, because it carries the context ("the patient we were talking about", "the request being filled in"). Clear it when the user starts a new chat, and send `new_conversation: true` with the first message of a new chat, so a card from an earlier chat can never be confirmed by mistake. `aiService.chat()` does this when `conversationId` is empty.

`client_message_id` is a fresh UUID per typed message. If the request times out, send the **same** body again with the **same** id. The server then returns the first reply (`duplicate: true`) instead of doing the work twice.

### Response

Every call returns `{ success: true, data: { conversation_id, reply, action?, duplicate?, answer, sources } }`. `answer` is the whole reply as text (kept for older clients); the widget uses `reply`. `reply` has this shape:

```json
{
  "kind": "confirmation",
  "language": "ar",
  "dir": "rtl",
  "text": "تمام، راح أحجز الموعد بالتفاصيل التالية:",
  "fields": [
    { "label": "المراجع", "value": "أحمد علي (07701111111)" },
    { "label": "التاريخ", "value": "11/11/2026 (الأربعاء)" },
    { "label": "الوقت", "value": "5:00 مساءً" },
    { "label": "الطبيب", "value": "د. محمد حسن" }
  ],
  "items": [],
  "options": [],
  "question": "تأكدلي، أنفذ الحجز؟",
  "notes": [],
  "action": {
    "id": "9d3c…", "tool": "create_appointment", "status": "pending",
    "expires_at": "2026-11-10T10:10:00+03:00",
    "confirm_label": "✅ تأكيد الحجز", "cancel_label": "❌ إلغاء"
  },
  "sources": [],
  "plain_text": "…everything above as one text, with the yes/no hint…"
}
```

Confirm and cancel responses also include `data.action.status`, the action's status after the call.

## Showing a reply

Show the server's strings as they are: `text`, field labels and values, button labels. They are already in the user's language, and the amounts, dates and names come from the API.

| Part | How to show it |
|---|---|
| `kind` | A badge, so the user can always tell these apart: `info` (ℹ️ from the system), `clarification` (no badge), `confirmation` (⏳ awaiting your confirmation), `success` (✅ done), `error` (⚠️ not done), `cancelled` (🚫 cancelled), `text` (no badge: greetings and general answers). |
| `dir` / `language` | Set `dir` on the bubble. A message can be English inside an Arabic UI, or the other way round. |
| `text` | Main line. Escape HTML. The widget's `parseMarkdown()` escapes first. |
| `fields` | Label and value rows. These are the confirmation summary and the results. |
| `items` | A list of `title`, `subtitle`, `meta`: appointments, unpaid invoices, free times. |
| `question` | Bold, after the details. |
| `notes` | Small muted lines, e.g. "this records money already received, nothing was collected". |
| `options` | Chips. Clicking one sends `{ option_id }` and adds the chip label to the chat as the user's message. Show options **only on the latest AI message**, and hide them once one is used. |
| `action` | Confirm and cancel buttons using `confirm_label` and `cancel_label`. |
| `sources` | The existing "N مصدر" badge, for general answers. |

## Confirmation card rules

1. **Nothing has happened yet** while a card is pending. Make that clear (badge + amber style).
2. **Disable both buttons while a request is in flight** and show a spinner on the one pressed.
3. **After the response**, replace the buttons with a status line taken from `data.action.status`:
   - `executed` → ✅ تم التنفيذ
   - `cancelled` → 🚫 تم الإلغاء
   - `failed` → ⚠️ ما تنفذت
   - `expired` → ⌛ انتهت مدة التأكيد
   - `unknown` / `executing` → ❓ ما وصل رد واضح, with a **"🔄 تحقق مرة ثانية"** button that calls confirm again. The server re-checks; it never repeats the write.
4. **Retire old cards.** Only one action can wait at a time. When any other reply arrives (the user typed "نعم", changed a detail, or asked something else), mark every older pending card as settled and remove its buttons. The widget's `retirePendingCards()` does this: a `success` reply gives "done", `cancelled` gives "cancelled", `error` gives "not done", anything else gives "replaced by a newer request". A re-shown card (same `action.id`) gets fresh buttons.
5. **Typed yes/no works too.** The user may type "نعم" / "اي" / "yes" instead of pressing. Send it as a normal message; the server matches it.
6. **Expired cards** come back as an `error` reply with a "🔄 جهز التأكيد من جديد" option chip. Clicking it rebuilds the card with fresh checks.
7. **Never guess a result.** If confirm times out, call `GET /ai/chat/actions/{id}`:
   - `executed` → say it was done.
   - `executing` / `unknown` → show the "check again" button.
   - Do **not** show success and do **not** resend the write yourself.

## Errors

| Situation | What the UI does |
|---|---|
| `404` on `messages` with a `conversation_id` | The conversation belongs to another login or clinic. Clear `conversation_id` and send the same message once more. |
| `409` | Still processing the previous message. Show `message_ar` / `message` ("الرسالة قيد المعالجة") and do not resend automatically. |
| Timeout or network error on `messages` | Resend once with the same `client_message_id`, then show the connection error. |
| `403` | Show `message_ar`, falling back to `message`. |
| `reply.kind === "error"` (HTTP 200) | A normal reply, e.g. a missing permission, a taken slot, or the API refusing. Show it as a red bubble, including its options. |

Do not add retries on confirm. Confirm is safe to call twice, but resending it automatically hides real problems from the user.

## Behaviour already in `AiChatWidget.vue`

- `conversationId` lives in the component; "محادثة جديدة" (new chat) clears it.
- `sendToAgent()` calls `aiService.chat({...})`. It generates `client_message_id`, sends `new_conversation` for the first message of a chat, retries once on timeout with the same id, and resets the conversation on 404.
- `runAgentTurn()` sets the typing indicator and maps 409 and 403 to messages.
- `confirmAction()` / `cancelAction()` drive the card states, and on a timeout they read `GET actions/{id}`.
- Suggested prompts are action-oriented: مواعيد اليوم، أوقات فارغة باچر، الفواتير غير المدفوعة، ملخص اليوم.
- The X-ray upload path still calls `/ai/analyze-xray` unchanged.

## Still to do (web)

- [ ] Click through the QA checklist below against a staging API that has run `php artisan tenants:migrate`.
- [ ] Dark mode: the widget has only light glass styles (existing). The new card colours follow them.
- [ ] Mobile web: the widget's floating button is hidden below 960 px (an existing rule), so the Capacitor build and narrow screens have no entry point. Show the same widget there (remove the rule or add an entry in the mobile menu that calls the widget's exposed `openChat()`). Do not build a second chat page.
- [ ] Optional: restore a conversation after a page reload with `GET /ai/chat/conversations/{id}` (the id would need to be kept in `sessionStorage`).
- [ ] Optional: Arabic and English labels for the badges and status lines from the i18n files. They are currently constants in the widget.

## QA checklist

Use a clinic that has patients, an active doctor and working hours.

| # | Type / do | Expect |
|---|---|---|
| 1 | احجزلي موعد لـ<patient> باچر الساعة 5 | Amber confirmation card (patient, tomorrow's date with weekday, 5:00 مساءً, doctor). Nothing in the schedule yet. |
| 2 | Press ✅ | Green "✅ تم حجز الموعد بنجاح." with booking ref; the card shows "تم التنفيذ"; the appointment is in the schedule. |
| 3 | Press ✅ again on the same card (it should be disabled; if forced through the API) | "هذي العملية تنفذت قبل…"; no second appointment. |
| 4 | أريد أحجز موعد, then answer each question | Asks patient, then day, then time (with chips), then shows the card. Earlier answers are not asked again. |
| 5 | احجز لمحمد … when two patients share the name | List of patients as chips; pick one; the card shows the picked one. |
| 6 | Book Ahmed for tomorrow at 5 PM | English, left-to-right card with "✅ Confirm booking". |
| 7 | شكد بذمة <patient>؟ then سجل تسديد 50 ألف | Balance info, then a payment card for the same patient with the remaining amount before and after. |
| 8 | <patient> دفع 50 | Chips "50,000 دينار" / "50 دينار"; nothing recorded until confirmed. |
| 9 | Card shown, then type "لا" | "تمام، لغيت العملية…"; the card shows cancelled. |
| 10 | Card shown, wait 11 minutes, press ✅ | Expired message with "🔄 جهز التأكيد من جديد". |
| 11 | Card shown, then type "خليه الساعة 6" | New card with 6:00; the old card shows "replaced". |
| 12 | Log in as a secretary without create-bill, then ask to record a payment | Red "ما عندك صلاحية تسجيل الدفعات." |
| 13 | شوفلي أوقات فارغة باچر | List of free times; tapping one starts a booking for that time. |
| 14 | Turn off the network, then send | Connection error bubble; after reconnecting, the same message works and is not processed twice. |
| 15 | Ask a general question (شنو أكثر دكتور عنده حالات؟) | A normal text answer, as before. |
| 16 | Open a patient → AI analysis | The same analysis as before, with no card and no question back. |
| 17 | Show a card, press "محادثة جديدة" (new chat), type "نعم" | "ما كو عملية بانتظار تأكيدك"; nothing is booked. |

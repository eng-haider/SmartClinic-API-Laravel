# AI Action Agent — Phone App (SmartClinic-App, Flutter)

How to upgrade the app's **existing** chat screen (`lib/screens/ai/ai_chat_screen.dart`) so staff can book, move and cancel appointments, record payments and add patients from the chat, always behind a confirmation card.

**It stays the same chatbot:** same screen, same `AiService` (`lib/services/ai_service.dart`), same endpoint `POST /ai/chat`. Do not add a separate agent screen, route or service. The code below is written against this app's own `ApiClient`, `S` strings and `AppColors`.

Backend reference: `AI_AGENT_DOCUMENTATION.md` (API repo). The web dashboard already does this, in `SmartClinic-Front/src/components/AiChatWidget.vue`, and can be used as a working reference.

## What changes for the user

Today the screen calls `POST /ai/chat` and shows one text answer. After this change, the same screen:

1. The user types a request: "احجزلي موعد لأحمد باچر الساعة 5", "سجل تسديد 50 ألف", "Book Ahmed for tomorrow at 5 PM".
2. The agent asks for anything missing or unclear, with tappable choices.
3. It shows a **confirmation card** with the exact details and ✅ / ❌ buttons.
4. Only after ✅ (or a typed "نعم" / "yes") does the server perform it through the clinic API. The card then shows the real result.

General questions keep working in the same conversation.

### Already working in the released app (no update needed)

The server keeps the old fields, so the app in the stores can already use actions, as text:

- It sends `{ question }` and shows `data.answer`. For a confirmation, `answer` holds the whole card as text, ending with "(اكتب «نعم» للتأكيد أو «لا» للإلغاء)".
- It sends no conversation id, so the server continues the user's conversation from the last 30 minutes. Typing "نعم" therefore confirms the card the user was just shown.
- The app's "new chat" button only clears the screen and does not start a new conversation on the server. A card from before "new chat" can still be confirmed with "نعم" within its 10-minute expiry. The update below fixes this with `new_conversation`.

The update adds real buttons, chips, status badges and safe retries.

## Requirements

- API deployed with the agent, and `php artisan tenants:migrate` run on the server.
- No new packages: Dio, `ApiClient`, `S` and `AppColors` are enough.

## API

`ApiClient` already adds `/tenant`, the token, `X-Tenant-ID` / `X-Clinic-ID` and `Accept-Language`. The paths below are what you pass to it.

| Call | Method & path | Body |
|---|---|---|
| Send a message | `POST /ai/chat` | `{ message, conversation_id? \| new_conversation: true, client_message_id }` |
| Tap an option chip | `POST /ai/chat` | `{ option_id, conversation_id }` |
| Confirm a card | `POST /ai/chat/actions/{id}/confirm` | `{ conversation_id }` |
| Cancel a card | `POST /ai/chat/actions/{id}/cancel` | `{ conversation_id }` |
| Action status (after a timeout) | `GET /ai/chat/actions/{id}` | — |

- `conversation_id` comes back from the first message. Keep it in the screen's state and send it every time, because it carries the context (the patient being discussed, the request being filled in). "New chat" clears it, and the first message after that sends `new_conversation: true`.
- `client_message_id` is a new random id per typed message. On a timeout, resend the **same** body with the **same** id: the server returns the first answer (`duplicate: true`) instead of processing it twice.

Response: `{ success: true, data: { conversation_id, reply, action?, duplicate?, answer, sources } }`. `answer` is the old plain-text field; the updated screen uses `reply`. Confirm and cancel also return `data.action.status`.

```json
"reply": {
  "kind": "confirmation",
  "language": "ar",
  "dir": "rtl",
  "text": "تمام، راح أحجز الموعد بالتفاصيل التالية:",
  "fields": [{ "label": "المراجع", "value": "أحمد علي (07701111111)" }, { "label": "الوقت", "value": "5:00 مساءً" }],
  "items": [{ "title": "6:00 مساءً — أحمد علي", "subtitle": "د. محمد حسن", "meta": "جديد" }],
  "options": [{ "id": "o3f9a1c2b7d", "label": "9:30 صباحاً" }],
  "question": "تأكدلي، أنفذ الحجز؟",
  "notes": [],
  "action": { "id": "9d3c…", "tool": "create_appointment", "status": "pending",
              "expires_at": "…", "confirm_label": "✅ تأكيد الحجز", "cancel_label": "❌ إلغاء" },
  "sources": [],
  "plain_text": "…"
}
```

`kind` is one of:

- `info`: data read from the system
- `clarification`: a question, often with options
- `confirmation`: waiting for approval
- `success`: the API carried it out
- `error`: not done (permission, taken slot, API refusal)
- `cancelled`
- `text`: greetings and general answers

## 1. Models: `lib/models/agent_reply.dart`

```dart
class AgentField {
  AgentField(this.label, this.value);
  final String label;
  final String value;
}

class AgentItem {
  AgentItem(this.title, this.subtitle, this.meta);
  final String title;
  final String? subtitle;
  final String? meta;
}

class AgentOption {
  AgentOption(this.id, this.label);
  final String id;
  final String label;
}

class AgentAction {
  AgentAction({
    required this.id,
    required this.status,
    required this.confirmLabel,
    required this.cancelLabel,
  });
  final String id;
  final String status;
  final String confirmLabel;
  final String cancelLabel;
}

class AgentReply {
  AgentReply({
    required this.kind,
    required this.language,
    required this.dir,
    required this.text,
    this.fields = const [],
    this.items = const [],
    this.options = const [],
    this.question,
    this.notes = const [],
    this.action,
    this.sources = 0,
  });

  final String kind;
  final String language;
  final String dir;
  final String text;
  final List<AgentField> fields;
  final List<AgentItem> items;
  final List<AgentOption> options;
  final String? question;
  final List<String> notes;
  final AgentAction? action;
  final int sources;

  bool get isRtl => dir != 'ltr';

  static List<Map> _maps(dynamic v) =>
      v is List ? v.whereType<Map>().toList() : const [];

  factory AgentReply.fromJson(Map json) {
    final action = json['action'];
    return AgentReply(
      kind: (json['kind'] ?? 'text').toString(),
      language: (json['language'] ?? 'ar').toString(),
      dir: (json['dir'] ?? 'rtl').toString(),
      text: (json['text'] ?? '').toString(),
      fields: _maps(json['fields'])
          .map((f) => AgentField('${f['label'] ?? ''}', '${f['value'] ?? ''}'))
          .toList(),
      items: _maps(json['items'])
          .map((i) => AgentItem('${i['title'] ?? ''}', i['subtitle']?.toString(), i['meta']?.toString()))
          .toList(),
      options: _maps(json['options'])
          .map((o) => AgentOption('${o['id']}', '${o['label'] ?? ''}'))
          .toList(),
      question: json['question']?.toString(),
      notes: json['notes'] is List ? (json['notes'] as List).map((n) => '$n').toList() : const [],
      action: action is Map
          ? AgentAction(
              id: '${action['id']}',
              status: '${action['status'] ?? 'pending'}',
              confirmLabel: '${action['confirm_label'] ?? '✅'}',
              cancelLabel: '${action['cancel_label'] ?? '❌'}',
            )
          : null,
      sources: json['sources'] is List ? (json['sources'] as List).length : 0,
    );
  }
}

/// One server answer: the reply plus the conversation and action status.
class AgentResult {
  AgentResult(this.conversationId, this.reply, this.actionStatus, this.duplicate);
  final String? conversationId;
  final AgentReply? reply;
  final String? actionStatus;
  final bool duplicate;

  factory AgentResult.fromResponse(Map<String, dynamic> res) {
    final data = res['data'] is Map ? res['data'] as Map : const {};
    return AgentResult(
      data['conversation_id']?.toString(),
      data['reply'] is Map ? AgentReply.fromJson(data['reply'] as Map) : null,
      data['action'] is Map ? (data['action'] as Map)['status']?.toString() : null,
      data['duplicate'] == true,
    );
  }
}
```

## 2. Service: extend the existing `lib/services/ai_service.dart`

Keep the class and its `ask()` method; add the methods below to it. All of them use the same chatbot endpoint, `/ai/chat`.

```dart
import 'dart:math';

import '../core/api_client.dart';
import '../models/agent_reply.dart';

/// The clinic chatbot (`/tenant/ai/chat`). It answers questions and prepares
/// actions; nothing changes until [confirm] is called for one.
class AiService {
  AiService(this._api);
  final ApiClient _api;

  // ask(String question) { ... }  ← keep as it is

  static const _timeout = Duration(seconds: 45);

  /// A random id per typed message; resending with it is safe.
  static String newMessageId() {
    final r = Random.secure();
    return List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
  }

  /// A message or an option tap. With no [conversationId], a new
  /// conversation is started (so an old card cannot be confirmed by mistake).
  Future<AgentResult> send({
    String? message,
    String? optionId,
    String? conversationId,
    String? clientMessageId,
  }) async {
    final res = await _api.post(
      '/ai/chat',
      data: {
        if (message != null) 'message': message,
        if (optionId != null) 'option_id': optionId,
        if (conversationId != null) 'conversation_id': conversationId else 'new_conversation': true,
        if (clientMessageId != null) 'client_message_id': clientMessageId,
      },
      timeout: _timeout,
    );
    return AgentResult.fromResponse(res);
  }

  Future<AgentResult> confirm(String actionId, {String? conversationId}) async {
    final res = await _api.post(
      '/ai/chat/actions/$actionId/confirm',
      data: {if (conversationId != null) 'conversation_id': conversationId},
      timeout: _timeout,
    );
    return AgentResult.fromResponse(res);
  }

  Future<AgentResult> cancel(String actionId, {String? conversationId}) async {
    final res = await _api.post(
      '/ai/chat/actions/$actionId/cancel',
      data: {if (conversationId != null) 'conversation_id': conversationId},
    );
    return AgentResult.fromResponse(res);
  }

  /// pending | executing | executed | failed | unknown | cancelled | expired
  Future<String> status(String actionId) async {
    final res = await _api.get('/ai/chat/actions/$actionId');
    final data = res['data'];
    return data is Map ? '${data['status'] ?? 'unknown'}' : 'unknown';
  }
}
```

`ApiClient` throws `ApiException` for any non-2xx answer, with `statusCode`. Timeouts and no connection come as `message == 'connection_error'` with a null `statusCode`.

## 3. Screen: `lib/screens/ai/ai_chat_screen.dart`

### Message model

Replace the private `_Message` with one that can hold a reply and a card state:

```dart
enum CardState { pending, loading, executed, cancelled, failed, expired, replaced, unknown }

CardState cardFromStatus(String? status) => switch (status) {
  'pending' => CardState.pending,
  'executed' => CardState.executed,
  'cancelled' => CardState.cancelled,
  'expired' => CardState.expired,
  'executing' || 'unknown' => CardState.unknown,
  _ => CardState.failed,
};

class _Message {
  _Message.user(this.text) : fromAi = false, reply = null, failed = false;
  _Message.ai(AgentReply this.reply)
    : text = reply.text,
      fromAi = true,
      failed = reply.kind == 'error',
      card = reply.action != null ? CardState.pending : null;
  _Message.error(this.text) : fromAi = true, reply = null, failed = true;

  final String text;
  final bool fromAi;
  final AgentReply? reply;
  final bool failed;
  CardState? card;
  bool optionsUsed = false;
  String? pressed; // 'confirm' | 'cancel'
}
```

### State and sending

```dart
// The screen already has: final _service = AiService(ApiClient.instance);
String? _conversationId;

void _newChat() => setState(() {
  _messages.clear();
  _conversationId = null;
});

/// Sends once; on a timeout resends with the same id; on a 404 (another
/// login or clinic) starts a fresh conversation.
Future<AgentResult> _ask({String? message, String? optionId}) async {
  final id = message != null ? AiService.newMessageId() : null;
  Future<AgentResult> call() => _service.send(
    message: message,
    optionId: optionId,
    conversationId: _conversationId,
    clientMessageId: id,
  );
  AgentResult result;
  try {
    result = await call();
  } on ApiException catch (e) {
    if (e.statusCode == 404 && _conversationId != null && message != null) {
      _conversationId = null;
      result = await call();
    } else if (e.message == 'connection_error' && id != null) {
      result = await call();
    } else {
      rethrow;
    }
  }
  _conversationId = result.conversationId ?? _conversationId;
  return result;
}

/// Only one action waits at a time: any newer answer settles older cards.
void _addReply(AgentReply reply, {bool fromCard = false}) {
  if (!fromCard) {
    final settled = switch (reply.kind) {
      'success' => CardState.executed,
      'cancelled' => CardState.cancelled,
      'error' => CardState.failed,
      _ => CardState.replaced,
    };
    for (final m in _messages) {
      if (m.card == CardState.pending && m.reply?.action?.id != reply.action?.id) {
        m.card = settled;
      }
    }
  }
  _messages.add(_Message.ai(reply));
}

Future<void> _send([String? preset]) async {
  final text = (preset ?? _input.text).trim();
  if (text.isEmpty || _thinking) return;
  _input.clear();
  setState(() {
    _messages.add(_Message.user(text));
    _thinking = true;
  });
  _toBottom();
  await _turn(() => _ask(message: text));
}

Future<void> _choose(_Message m, AgentOption option) async {
  if (_thinking) return;
  setState(() {
    m.optionsUsed = true;
    _messages.add(_Message.user(option.label));
    _thinking = true;
  });
  _toBottom();
  await _turn(() => _ask(optionId: option.id));
}

Future<void> _turn(Future<AgentResult> Function() request) async {
  final s = S.of(context);
  try {
    final result = await request();
    if (!mounted) return;
    setState(() {
      if (result.reply != null) {
        _addReply(result.reply!);
      } else {
        _messages.add(_Message.error(s('ai_error')));
      }
    });
  } on ApiException catch (e) {
    if (!mounted) return;
    setState(() => _messages.add(_Message.error(_errorText(s, e))));
  } finally {
    if (mounted) setState(() => _thinking = false);
    _toBottom();
  }
}

String _errorText(S s, ApiException e) => switch (e.statusCode) {
  409 => s('agent_busy'),
  403 => e.message.isNotEmpty ? e.message : s('ai_not_enabled'),
  _ => e.message == 'connection_error' ? s('connection_error') : s('ai_error'),
};
```

### Confirm and cancel

```dart
Future<void> _confirm(_Message m) async {
  final action = m.reply!.action!;
  final s = S(m.reply!.language);
  setState(() {
    m.card = CardState.loading;
    m.pressed = 'confirm';
    _thinking = true;
  });
  try {
    final r = await _service.confirm(action.id, conversationId: _conversationId);
    if (!mounted) return;
    setState(() {
      m.card = cardFromStatus(r.actionStatus);
      if (r.reply != null) _addReply(r.reply!, fromCard: true);
    });
    if (m.card == CardState.executed) HapticFeedback.mediumImpact();
  } on ApiException catch (e) {
    if (!mounted) return;
    if (e.message != 'connection_error') {
      setState(() {
        m.card = e.statusCode == 404 ? CardState.failed : CardState.pending;
        _messages.add(_Message.error(_errorText(S.of(context), e)));
      });
    } else {
      // No answer: ask the server what happened. Never assume success, never resend.
      String status = 'unknown';
      try {
        status = await _service.status(action.id);
      } on ApiException {
        // still unknown
      }
      if (!mounted) return;
      setState(() {
        m.card = cardFromStatus(status);
        if (m.card == CardState.executed) {
          _messages.add(_Message.error(s('agent_checked_done')));
        } else if (m.card == CardState.unknown) {
          _messages.add(_Message.error(s('agent_no_answer')));
        }
      });
    }
  } finally {
    if (mounted) setState(() => _thinking = false);
    _toBottom();
  }
}

Future<void> _cancel(_Message m) async {
  setState(() {
    m.card = CardState.loading;
    m.pressed = 'cancel';
    _thinking = true;
  });
  try {
    final r = await _service.cancel(m.reply!.action!.id, conversationId: _conversationId);
    if (!mounted) return;
    setState(() {
      m.card = cardFromStatus(r.actionStatus ?? 'cancelled');
      if (r.reply != null) _addReply(r.reply!, fromCard: true);
    });
  } on ApiException catch (e) {
    if (!mounted) return;
    setState(() {
      m.card = CardState.pending;
      _messages.add(_Message.error(_errorText(S.of(context), e)));
    });
  } finally {
    if (mounted) setState(() => _thinking = false);
    _toBottom();
  }
}
```

The "checked: done" line uses `_Message.error` only for its plain-text layout. If you want it green, add a `_Message.note` constructor.

### Reply bubble

Build AI messages that have a `reply` with this widget. Keep the existing bubble for user messages and for `_Message.error`. Show options only on the **last** AI message.

```dart
class _AgentBubble extends StatelessWidget {
  const _AgentBubble({
    required this.message,
    required this.isLatest,
    required this.busy,
    required this.onConfirm,
    required this.onCancel,
    required this.onOption,
  });

  final _Message message;
  final bool isLatest;
  final bool busy;
  final VoidCallback onConfirm;
  final VoidCallback onCancel;
  final ValueChanged<AgentOption> onOption;

  @override
  Widget build(BuildContext context) {
    final reply = message.reply!;
    final s = S(reply.language); // the reply's language, not the app's
    final (Color tint, Color soft, String? badge) = switch (reply.kind) {
      'confirmation' => (AppColors.warning, AppColors.warningSoft, s('agent_badge_confirmation')),
      'success' => (AppColors.success, AppColors.successSoft, s('agent_badge_success')),
      'error' => (AppColors.danger, AppColors.dangerSoft, s('agent_badge_error')),
      'cancelled' => (AppColors.muted, AppColors.surfaceSoft, s('agent_badge_cancelled')),
      'info' => (AppColors.blue, AppColors.surface, s('agent_badge_info')),
      _ => (AppColors.border, AppColors.surface, null),
    };

    return Directionality(
      textDirection: reply.isRtl ? TextDirection.rtl : TextDirection.ltr,
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: soft,
          borderRadius: BorderRadius.circular(16),
          // Uniform border: Flutter asserts on a one-sided border with a radius
          border: Border.all(color: tint.withValues(alpha: 0.45), width: 1.2),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (badge != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(badge, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: tint)),
              ),
            SelectableText(reply.text, style: TextStyle(color: AppColors.text, height: 1.5)),
            if (reply.fields.isNotEmpty) ...[
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.surfaceElevated,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  children: [
                    for (final f in reply.fields)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(f.label, style: TextStyle(color: AppColors.secondary, fontSize: 12.5)),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                f.value,
                                textAlign: TextAlign.end,
                                style: TextStyle(color: AppColors.text, fontWeight: FontWeight.w700, fontSize: 12.5),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
            for (final item in reply.items)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text.rich(TextSpan(children: [
                  TextSpan(text: item.title, style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.text)),
                  if (item.subtitle != null) TextSpan(text: '  ${item.subtitle}', style: TextStyle(color: AppColors.secondary)),
                  if (item.meta != null) TextSpan(text: '  · ${item.meta}', style: TextStyle(color: AppColors.muted)),
                ])),
              ),
            if (reply.question != null) ...[
              const SizedBox(height: 8),
              Text(reply.question!, style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.text)),
            ],
            for (final note in reply.notes)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(note, style: TextStyle(fontSize: 11.5, color: AppColors.muted)),
              ),
            if (reply.action != null) _actionBar(s, reply.action!),
            if (isLatest && !message.optionsUsed && reply.options.isNotEmpty && reply.action == null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final o in reply.options)
                      ActionChip(label: Text(o.label), onPressed: busy ? null : () => onOption(o)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _actionBar(S s, AgentAction action) {
    final state = message.card;
    if (state == CardState.pending || state == CardState.loading) {
      final loading = state == CardState.loading;
      Widget spinner() => const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2));
      return Padding(
        padding: const EdgeInsets.only(top: 10),
        child: Row(
          children: [
            Expanded(
              child: FilledButton(
                style: FilledButton.styleFrom(backgroundColor: AppColors.success, minimumSize: const Size.fromHeight(44)),
                onPressed: loading || busy ? null : onConfirm,
                child: loading && message.pressed == 'confirm' ? spinner() : Text(action.confirmLabel),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(44)),
                onPressed: loading || busy ? null : onCancel,
                child: loading && message.pressed == 'cancel' ? spinner() : Text(action.cancelLabel),
              ),
            ),
          ],
        ),
      );
    }

    final label = s('agent_state_${state?.name ?? 'failed'}');
    final color = switch (state) {
      CardState.executed => AppColors.success,
      CardState.failed || CardState.unknown => AppColors.danger,
      _ => AppColors.secondary,
    };
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Expanded(child: Text(label, style: TextStyle(fontWeight: FontWeight.w700, color: color))),
          if (state == CardState.unknown)
            TextButton(onPressed: busy ? null : onConfirm, child: Text(s('agent_check_again'))),
        ],
      ),
    );
  }
}
```

In the list builder:

```dart
final latestAi = _messages.lastIndexWhere((m) => m.fromAi);
// ...
if (m.reply != null) {
  return _AgentBubble(
    message: m,
    isLatest: index == latestAi,
    busy: _thinking,
    onConfirm: () => _confirm(m),
    onCancel: () => _cancel(m),
    onOption: (o) => _choose(m, o),
  );
}
```

Remove the old `_retry()` for agent replies, or keep it only for `_Message.error` after a connection error. A typed message resent with a new id is still safe, because each action runs at most once on the server.

## 4. Strings: add to `lib/l10n/strings.dart`

| Key | ar | en |
|---|---|---|
| `agent_badge_info` | ℹ️ معلومات من النظام | ℹ️ From the system |
| `agent_badge_confirmation` | ⏳ بانتظار تأكيدك | ⏳ Awaiting your confirmation |
| `agent_badge_success` | ✅ تم التنفيذ | ✅ Done |
| `agent_badge_error` | ⚠️ لم يتم | ⚠️ Not done |
| `agent_badge_cancelled` | 🚫 ملغي | 🚫 Cancelled |
| `agent_state_executed` | ✅ تم التنفيذ | ✅ Done |
| `agent_state_cancelled` | 🚫 تم الإلغاء، ما تغير شي | 🚫 Cancelled, nothing changed |
| `agent_state_failed` | ⚠️ ما تنفذت | ⚠️ Not done |
| `agent_state_expired` | ⌛ انتهت مدة التأكيد | ⌛ Confirmation expired |
| `agent_state_replaced` | ↩️ انلغت بطلب أحدث | ↩️ Replaced by a newer request |
| `agent_state_unknown` | ❓ ما وصل رد واضح | ❓ No clear answer yet |
| `agent_check_again` | 🔄 تحقق مرة ثانية | 🔄 Check again |
| `agent_checked_done` | ✅ تأكدت من النظام: العملية تنفذت. | ✅ Checked with the system: it was done. |
| `agent_no_answer` | ما وصل رد من الخادم. اضغط «تحقق مرة ثانية» قبل لا تعيد الطلب. | No answer from the server. Tap “Check again” before asking again. |
| `agent_busy` | الرسالة السابقة قيد المعالجة، لحظة. | Your previous message is still being processed. |

Suggested-prompt changes, so users discover actions:

- `ai_q1` شنو مواعيد اليوم؟ / What are today's appointments?
- `ai_q2` شوفلي أوقات فارغة باچر / Free times tomorrow
- `ai_q3` طلعلي الفواتير غير المدفوعة / Unpaid invoices
- `ai_q4` ملخص اليوم / Today's summary
- `ai_input_hint` اكتب سؤالك أو طلبك... / Ask or request something...

## Rules the app must keep

1. **Show server text as is.** Labels, values, amounts, dates and button text come from the server in the reply's language. Do not reformat or translate them.
2. **Nothing happens before ✅.** Never call confirm automatically, and never call it more than once per tap.
3. **Disable both card buttons while a request is in flight.**
4. **One waiting card at a time.** Any newer answer settles older cards (`_addReply` does this). The server cancels them too, so an old card's ✅ would only return an error.
5. **Typed yes/no is valid.** "نعم" / "اي" / "yes" sent as text confirms the waiting card on the server.
6. **On a confirm timeout, ask, don't assume.** Use `GET actions/{id}`; show "check again" for `unknown` and `executing`.
7. **Per-message direction.** An English reply in the Arabic app (and the reverse) uses its own `dir`.
8. **Expired cards** come back as an `error` reply with a "🔄 جهز التأكيد من جديد" option chip. Tapping it rebuilds the card with fresh checks.
9. **Keep `conversation_id` only in memory.** Clear it on "new chat" and on logout. Do not share it between users.

## Error handling summary

| Case | App behaviour |
|---|---|
| 404 on messages | Reset `conversation_id`, send once more (`_ask` does this). |
| 409 | Show `agent_busy`; do not resend automatically. |
| `connection_error` on messages | Resend once with the same `client_message_id`, then show `connection_error`. |
| `connection_error` on confirm | Read `GET actions/{id}`; never resend confirm automatically. |
| 403 | The server's message. |
| 401 | The existing `onUnauthorized` logout. |
| `reply.kind == "error"` (HTTP 200) | A normal red bubble, with options if it has any. |

## Tests to add

- `test/agent_reply_test.dart`: parse a confirmation, an info list and an error reply. Check `isRtl`, fields, options and `action`.
- Widget test with `ApiClient.instance.useHttpAdapter(...)`:
  - type → card shown → ✅ → success bubble and the card shows "done";
  - ❌ → cancelled;
  - a confirm timeout followed by `GET actions/{id}` returning `executed` shows "checked: done", without a second confirm call.

Manual checks, against staging after `tenants:migrate`:

| # | Do | Expect |
|---|---|---|
| 1 | احجزلي موعد لـ<patient> باچر الساعة 5 → ✅ | Card, then "✅ تم حجز الموعد بنجاح." with booking ref; the appointment appears in Reservations. |
| 2 | Tap ✅ twice quickly | One appointment only. |
| 3 | أريد أحجز موعد and answer step by step | Asked patient → day → time (chips) → card. |
| 4 | A name shared by two patients | Patient chips; the card shows the one tapped. |
| 5 | شكد بذمة <patient>؟ then سجل تسديد 50 ألف → ✅ | Balance, then a payment card with the remaining amount before and after, then the recorded payment and its note. |
| 6 | <patient> دفع 50 | Chips 50,000 / 50; nothing recorded until confirmed. |
| 7 | Card, then type "لا" | Cancelled; the card shows cancelled. |
| 8 | Card, then "خليه الساعة 6" | New card at 6:00; the old card shows "replaced". |
| 9 | Book Ahmed tomorrow at 5 PM | English, left-to-right card. |
| 10 | Airplane mode on ✅, then off and tap "check again" | Correct final state, no duplicate appointment. |
| 11 | Secretary without payment permission asks to record a payment | Red "ما عندك صلاحية تسجيل الدفعات." |
| 12 | A general question | A normal text answer, as before. |
| 13 | Card shown → "new chat" → type "نعم" | "ما كو عملية بانتظار تأكيدك"; nothing is booked. |
| 14 | Book for a name that doesn't exist → نعم → نعم → نعم | Add-patient card, then the patient is added and the booking card follows, then booked. Also works in the released app by typing. |

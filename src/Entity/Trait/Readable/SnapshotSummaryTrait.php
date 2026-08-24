<?php

namespace App\Entity\Trait\Readable;

/**
 * Человекочитаемое представление снимка "поле: было → стало" — общая логика
 * для сущностей, хранящих $snapshot изменений Ticket. Понимает ДВЕ формы:
 *   1. Плоский {field: {old, new}} — EntityRevision: одна запись = одна
 *      правка, снимок описывает ровно её.
 *   2. Список [{at, changes: {field: {old, new}}}, ...] — TicketApproval:
 *      одна запись объединяет НЕСКОЛЬКО правок за окно повторного
 *      использования (см. TicketApproval::appendSnapshot) — каждая правка
 *      добавляется отдельным элементом списка, ничего не сливается, поэтому
 *      строки снабжаются меткой времени той конкретной правки.
 * Вынесено сюда, чтобы не дублировать форматирование/экранирование в двух
 * местах — раньше жило только в EntityRevision::getSnapshotSummary().
 *
 * Использующий класс должен предоставить:
 *   getSnapshot(): array               — сам снимок
 *   getFieldLabels(): array<string, string> — перевод ключей поля в
 *                                        человекочитаемые подписи
 */
trait SnapshotSummaryTrait
{
    abstract public function getSnapshot(): array;

    /** @return array<string, string> */
    abstract protected function getFieldLabels(): array;

    /**
     * @param string     $separator        Разделитель строк — "<br>" для HTML-контекста
     *                                     (EasyAdmin Trix, email), "\n" для чистого
     *                                     текста (Telegram — там "<br>" не рендерится).
     * @param array|null $snapshotOverride Рендерить этот снимок вместо getSnapshot() —
     *                                     используется getLatestChangeSummary() ниже,
     *                                     чтобы не дублировать всю логику форматирования
     *                                     ради рендера ОДНОЙ, а не всех, правок.
     */
    public function getSnapshotSummary(string $separator = '<br>', ?array $snapshotOverride = null): string
    {
        $snapshot = $snapshotOverride ?? $this->getSnapshot();

        if (!$snapshot) return '—';

        // Пакетное удаление фото (см. AbstractApiHelperController::
        // logImagesDeletion) — один EntityRevision на ВСЕ фото, удалённые
        // за один запрос, а не по записи на каждое. Форма снапшота другая:
        // список путей, а не "поле: было → стало", разбираем отдельно.
        // TicketApproval такой снимок никогда не пишет (это только для
        // EntityRevision), но проверка безвредна для обоих использующих
        // класс — просто никогда не сработает у TicketApproval.
        if (isset($snapshot['images']) && is_array($snapshot['images'])) {
            $lines = [];
            foreach ($snapshot['images'] as $entry) {
                $path = is_array($entry) ? ($entry['image'] ?? '') : (string) $entry;
                $lines[] = htmlspecialchars("Фото: {$path}", ENT_QUOTES, 'UTF-8');
            }
            return implode($separator, $lines);
        }

        // Список отдельных правок (TicketApproval::appendSnapshot) —
        // числовой список, каждый элемент которого — {at, changes}, в
        // отличие от плоского {field: {old,new}} у EntityRevision (тот
        // всегда ассоциативный по именам полей, никогда array_is_list).
        if (array_is_list($snapshot) && isset($snapshot[0]['changes'])) {
            $lines = [];
            foreach ($snapshot as $entry) {
                $when = isset($entry['at']) ? $this->formatSnapshotTimestamp($entry['at']) : null;
                foreach ((array) ($entry['changes'] ?? []) as $field => $value) {
                    $lines[] = htmlspecialchars($this->renderSnapshotLine($field, $value, $when), ENT_QUOTES, 'UTF-8');
                }
            }
            return $lines ? implode($separator, $lines) : '—';
        }

        $lines = [];
        foreach ($snapshot as $field => $value) {
            $lines[] = htmlspecialchars($this->renderSnapshotLine($field, $value), ENT_QUOTES, 'UTF-8');
        }

        return implode($separator, $lines);
    }

    /**
     * То же самое, но БЕЗ HTML — для мест, где значение попадает не прямо
     * в разметку (crud/field/text, письмо), а в HTML-АТРИБУТ или в форменный
     * виджет (EasyAdmin на EDIT-странице всегда рендерит поле через реальный
     * Symfony Form-виджет, а не через setTemplateName() — тот работает
     * только для INDEX/DETAIL, см. докблок класса), которые сами по себе
     * ОДИН раз экранируют переданное значение. Отдать туда уже
     * экранированный getSnapshotSummary() означало бы двойное экранирование
     * (ровно так и было: "<br>" превращался в видимый текст "&lt;br&gt;",
     * а "&lt;div&gt;" — в "&amp;lt;div&amp;gt;", проверено живьём).
     * Разделитель — настоящий перевод строки: обычный <textarea>
     * (white-space: pre) показывает его как перенос строки сам по себе,
     * без "<br>".
     */
    public function getSnapshotSummaryPlain(): string
    {
        return htmlspecialchars_decode($this->getSnapshotSummary("\n"), ENT_QUOTES);
    }

    /**
     * То же самое, но только САМАЯ ПОСЛЕДНЯЯ правка — для уведомлений
     * (Telegram/email): при переиспользовании TicketApproval в пределах
     * TICKET_APPROVAL_REUSE_WINDOW (см. TicketListener::resolveApproval)
     * список правок растёт с каждым новым редактированием, и уведомление,
     * до сих пор показывавшее ВСЮ накопленную историю, рано или поздно
     * упиралось в защитный лимит длины сообщения и обрезалось прямо
     * посреди строки — проверено живьём. Для уведомления это и не нужно:
     * оно про "что изменилось только что", а полную историю видно по
     * ссылке в самой заявке (снимок целиком остаётся в getSnapshotSummary()
     * — для админки это не трогаем). Для плоского {field:{old,new}}
     * (EntityRevision — там всегда ровно одна правка на запись) совпадает
     * с getSnapshotSummary() — разбивать нечего.
     */
    public function getLatestChangeSummary(string $separator = '<br>'): string
    {
        $snapshot = $this->getSnapshot();

        if (array_is_list($snapshot) && isset($snapshot[0]['changes'])) {
            $snapshot = $snapshot ? [end($snapshot)] : [];
        }

        return $this->getSnapshotSummary($separator, $snapshot);
    }

    /** getLatestChangeSummary(), но без HTML — та же надобность, что у getSnapshotSummaryPlain(). */
    public function getLatestChangeSummaryPlain(): string
    {
        return htmlspecialchars_decode($this->getLatestChangeSummary("\n"), ENT_QUOTES);
    }

    private function renderSnapshotLine(string $field, mixed $value, ?string $when = null): string
    {
        $label  = $this->getFieldLabels()[$field] ?? $field;
        $prefix = $when !== null ? "[{$when}] " : '';

        if (is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value)) {
            return $prefix . "{$label}: " . $this->stringifySnapshotValue($value['old']) . ' → ' . $this->stringifySnapshotValue($value['new']);
        }

        return $prefix . "{$label}: " . $this->stringifySnapshotValue($value);
    }

    /** 'at' — DateTimeImmutable::ATOM (см. TicketApproval::appendSnapshot) → "24.08 17:23". */
    private function formatSnapshotTimestamp(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->format('d.m H:i');
        } catch (\Exception) {
            return $iso;
        }
    }

    private function stringifySnapshotValue(mixed $value): string
    {
        if ($value === null)  return '(пусто)';
        if (is_bool($value))  return $value ? 'да' : 'нет';
        if (is_scalar($value)) return (string) $value;

        // Ссылка на справочник вида {id, title} (см. TicketListener::
        // toSnapshotValue/geoRef) — печатаем title, если он есть, чтобы
        // "Поле: ... → ..." было читаемо, а не сырым JSON.
        if (is_array($value) && array_key_exists('id', $value)) {
            return $value['title'] ?? ('#' . $value['id']);
        }

        // Один адрес целиком — {province: ?{id,title}, city: ?{id,title},
        // ...} (см. TicketListener::addressSnapshot) — не список и не
        // {id,title} сам по себе, но каждое непустое значение внутри именно
        // такой ссылкой и является. Склеиваем в "ГРРП, Вахдат" — так же,
        // как Address::__toString() собирает читаемую метку из уровней.
        if (is_array($value) && !array_is_list($value) && $this->looksLikeAddressEntry($value)) {
            $parts = array_filter(array_map(
                fn(mixed $ref): ?string => is_array($ref) ? ($ref['title'] ?? ('#' . $ref['id'])) : null,
                array_filter($value, fn(mixed $ref): bool => $ref !== null),
            ));
            return $parts ? implode(', ', $parts) : '(пусто)';
        }

        // Список адресов (массив записей вида выше) или любой другой
        // числовой список — печатаем через запятую, пропуская пустые.
        if (is_array($value) && array_is_list($value)) {
            $parts = array_map(fn(mixed $item): string => $this->stringifySnapshotValue($item), $value);
            $parts = array_filter($parts, fn(string $p): bool => $p !== '(пусто)');
            return $parts ? implode(', ', $parts) : '(пусто)';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * "Похоже на один адрес": ассоциативный массив, где каждое непустое
     * значение — само {id, ...}-ссылка (не хардкодим точные ключи
     * province/city/.../village — это защитит от рассинхрона, если набор
     * уровней когда-нибудь расширят в TicketListener::addressSnapshot()).
     */
    private function looksLikeAddressEntry(array $value): bool
    {
        if (!$value) return false;

        foreach ($value as $ref) {
            if ($ref !== null && !(is_array($ref) && array_key_exists('id', $ref))) {
                return false;
            }
        }

        return true;
    }
}

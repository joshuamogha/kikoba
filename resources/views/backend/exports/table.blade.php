<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $title }} - {{ $groupName }}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #212529; margin: 0; }
    h1 { font-size: 18px; margin: 0; }
    h2 { font-size: 13px; margin: 14px 0 4px; }
    .meta { color: #6c757d; margin: 2px 0 12px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 1px solid #adb5bd; padding: 4px 6px; text-align: left; }
    th { background: #e9ecef; }
    td.num, th.num { text-align: right; }
    tfoot td { font-weight: bold; background: #f8f9fa; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .bar { margin-bottom: 10px; }
    @media print { .bar { display: none; } }
  </style>
</head>
<body>
  <div class="bar"><button onclick="window.print()">Print / Save as PDF</button></div>
  <h1>{{ $groupName }}</h1>
  <div class="meta">{{ $title }}@if ($subtitle !== '') &middot; {{ $subtitle }}@endif &middot; printed {{ now()->toDateString() }}</div>
  @foreach ($sections as $section)
    @if ($section['title'])
      <h2>{{ $section['title'] }}</h2>
    @endif
    <table>
      <thead>
        <tr>
          @foreach ($section['headings'] as $heading)
            <th @class(['num' => $loop->index > 0 && is_float($section['rows'][0][$loop->index] ?? null)])>{{ $heading }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse ($section['rows'] as $row)
          <tr>
            @foreach ($row as $cell)
              <td @class(['num' => is_float($cell) || is_int($cell) && $loop->index > 0])>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="{{ max(1, count($section['headings'])) }}">Nothing found.</td></tr>
        @endforelse
      </tbody>
      @if ($section['totals'] && count($section['rows']))
        <tfoot>
          <tr>
            @foreach ($section['totals'] as $cell)
              <td @class(['num' => is_float($cell) || is_int($cell)])>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>
            @endforeach
          </tr>
        </tfoot>
      @endif
    </table>
  @endforeach
  <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>

{{-- The button row at the end of an email, the same look in every one. $links: list of ['label', 'url',
     'variant'] (variant: '' blue, 'secondary' grey, 'warning' yellow). Inline colours too, for mail clients that
     drop <style>. --}}
@php
    $actionLinkStyles = [
        ''          => 'background:#0d6efd;color:#fff !important;',
        'secondary' => 'background:#6c757d;color:#fff !important;',
        'warning'   => 'background:#ffc107;color:#000 !important;',
    ];
@endphp
<div class="actions">
    @foreach ($links as $link)
        <a href="{{ $link['url'] }}" class="action-link {{ $link['variant'] ?? '' }}" target="_blank"
           style="{{ $actionLinkStyles[$link['variant'] ?? ''] ?? '' }}text-decoration:none;">{{ $link['label'] }}</a>
    @endforeach
</div>

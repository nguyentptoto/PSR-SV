<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<h1>HỆ THỐNG PHÊ DUYỆT PR</h1>
@else
{{ $slot }}
@endif
</a>
</td>
</tr>

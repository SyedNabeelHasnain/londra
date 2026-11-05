<ol class="process-steps">
@for($step = 1; $step <= 3; $step++)
<li><span class="process-number" aria-hidden="true">0{{ $step }}</span><h3>{{ $u['process_'.$step] }}</h3><p>{{ $u['process_detail_'.$step] }}</p></li>
@endfor
</ol>

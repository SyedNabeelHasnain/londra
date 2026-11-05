@php
    $luggageItems = [];
    if (!empty($luggageVehicle['luggage_approved'])) {
        foreach (['large_luggage' => 'large_cases', 'cabin_luggage' => 'cabin_bags'] as $luggageField => $luggageLabel) {
            $luggageCount = $luggageVehicle[$luggageField] ?? null;
            $luggageNumber = $luggageCount === null ? false : filter_var($luggageCount, FILTER_VALIDATE_INT);
            if ($luggageNumber === false || $luggageNumber < 0) { continue; }
            $luggageItems[] = ['label' => $u[$luggageLabel], 'value' => $luggageNumber];
        }
    }
@endphp
@if($luggageItems)
<ul class="approved-luggage">
@foreach($luggageItems as $luggageItem)
<li><span>{{ $luggageItem['label'] }}</span><strong>{{ $luggageItem['value'] }}</strong></li>
@endforeach
</ul>
@endif

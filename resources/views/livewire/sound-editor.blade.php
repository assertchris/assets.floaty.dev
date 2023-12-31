<?php

use App\Models\Sound;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public int $length = 1;

    public array $notes = [];

    public int $instrument = 1;

    public Sound $sound;

    public array $errors = [];

    public bool $snapToNotes = true;

    public bool $snapToSharps = true;

    public function mount(): void
    {
        for ($i = 0; $i < 32; $i++) {
            $this->notes[$i] = $this->sound->notes[$i];
        }

        $this->length = $this->sound->length;
    }

    public function save(): void
    {
        $this->sound->length = $this->length;
        $this->sound->notes = $this->notes;
        $this->sound->save();
    }

    public function changeSlug(string $newSlug): void
    {
        $validator = validator()->make([
            'newSlug' => $newSlug,
        ], [
            'newSlug' => Rule::unique('sounds', 'slug')
                ->ignore($this->sound)
                ->where('project_id', $this->sound->project->id),
        ]);

        $this->errors['slug'] = null;

        if (!$validator->fails()) {
            $this->sound->slug = $newSlug;
            $this->sound->save();
        } else {
            $this->errors['slug'] = __('This slug is already taken');
        }
    }

    public function changeLength(int $newLength): void
    {
        $validator = validator()->make([
            'newLength' => $newLength,
        ], [
            'newLength' => ['numeric', 'min:1'],
        ]);

        $this->errors['length'] = null;

        if (!$validator->fails()) {
            $this->length = $newLength;
            $this->sound->length = $newLength;
            $this->sound->save();
        } else {
            $this->errors['length'] = __('The length must be at least 1');
        }
    }
};

?>
<div
    x-data="{
        length: @entangle('length').live,
        notes: @entangle('notes').live,
        instrument: @entangle('instrument').live,
        currentButton: null,
        resetTimer: null,
        snapToNotes: @entangle('snapToNotes').live,
        snapToSharps: @entangle('snapToSharps').live,
        minFrequency: {{ config('floaty.frequencies.min') }},
        maxFrequency: {{ config('floaty.frequencies.max') }},
        noteFrequencies: {{ json_encode(config('floaty.notes')) }},
        init() {
            this.updatePercentages();
            $watch('snapToNotes', () => this.updatePercentages());
            $watch('snapToSharps', () => this.updatePercentages());

            window.NProgress.done();
            setTimeout(() => window.NProgress.remove(), 500);
        },
        updatePercentages() {
            this.percentages = [];

            var diff = this.maxFrequency - this.minFrequency;

            for (var i = 0; i < this.noteFrequencies.length; i++) {
                var frequency = this.noteFrequencies[i][1];

                if (frequency > this.maxFrequency) {
                    continue;
                }

                if (!this.snapToSharps && this.noteFrequencies[i][2]) {
                    continue;
                }

                var percentage = (frequency / diff) * 100;

                this.percentages.push(percentage);
            }
        },
        @if (user() && user()->is($this->sound->project->user))
        frequencyClear(event, i) {
            event.preventDefault();
            this.notes[i][0] = 0;
        },
        frequencyMousedown(event, i) {
            event.preventDefault();

            if (event.button === 0) {
                this.currentButton = 'left';
            }

            if (event.button === 2) {
                this.currentButton = 'right';
            }

            this.frequencyMousemove(event, i)
        },
        frequencyMousemove(event, i) {
            clearTimeout(this.resetTimer);

            if (this.currentButton === 'left') {
                var percent = 100 - (event.pageY - event.target.offsetTop) / 400 * 100;

                if (this.snapToNotes) {
                    percent = window.closest(percent, this.percentages);
                }

                this.notes[i][0] = percent;
                this.notes[i][2] = this.instrument;
            }
            else if (this.currentButton === 'right') {
                this.notes[i][0] = 0;
            }
        },
        volumeClear(event, i) {
            event.preventDefault();
            this.notes[i][1] = 0;
        },
        volumeMousedown(event, i) {
            event.preventDefault();

            if (event.button === 0) {
                this.currentButton = 'left';
            }

            if (event.button === 2) {
                this.currentButton = 'right';
            }

            this.volumeMousemove(event, i)
        },
        volumeMousemove(event, i) {
            clearTimeout(this.resetTimer);

            if (this.currentButton === 'left') {
                var percent = 100 - (event.pageY - event.target.offsetTop) / 200 * 100;
                this.notes[i][1] = percent / 100;
            }
            else if (this.currentButton === 'right') {
                this.notes[i][1] = 0;
            }
        },
        stop() {
            this.currentButton = null;
            this.$wire.save();
        },
        @endif
        play() {
            var instance = new window.Engine();

            var sounds = {
                '{{ $this->sound->slug ?? $this->sound->name }}': [
                    this.length,
                    ...this.notes,
                ],
            };

            var options = {
                sprites: {},
                sounds,
                init: function() {
                    this.sfx('{{ $this->sound->slug ?? $this->sound->name }}');
                },
                update: () => {},
                draw: () => {},
                target: document.querySelector('.instance'),
            };

            instance.enableAudio().then(() => {
                instance.start(options);
            });
        },
        reset() {
            this.resetTimer = setTimeout(() => this.currentButton = null, 1000 * 2.5);
        },
    }"
    class="flex w-full"
>
    <div class="flex w-full h-full p-4 pl-0 space-x-4 items-start">
        <div
            class="flex flex-col h-[600px] w-2/3 select-text space-y-4"
            x-on:mouseup="reset"
        >
            <div class="flex flex-row min-h-[400px] w-full">
                @for ($i = 0; $i < 32; $i++)
                    <div
                        @if (user() && user()->is($this->sound->project->user))
                            x-on:contextmenu="frequencyClear($event, {{ $i }})"
                            x-on:mousedown="frequencyMousedown($event, {{ $i }})"
                            x-on:mousemove="frequencyMousemove($event, {{ $i }})"
                            x-on:mouseup="stop"
                        @endif
                        class="flex h-full bg-gray-100 w-[3.125%] relative"
                    >
                        <div
                            class="absolute w-full bg-gray-200 bottom-0 pointer-events-none"
                            x-bind:style="{ height: notes[{{ $i }}][0] + '%' }"
                        >
                            <div
                                class="h-[3px] w-full"
                                x-bind:class="{
                                    'bg-red-300': notes[{{ $i }}][2] == 0,
                                    'bg-green-300': notes[{{ $i }}][2] == 1,
                                    'bg-purple-300': notes[{{ $i }}][2] == 2,
                                    'bg-blue-300': notes[{{ $i }}][2] == 3,
                                }"
                            >
                                &nbsp;
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
            <div class="flex flex-row min-h-[200px] w-full">
                @for ($i = 0; $i < 32; $i++)
                    <div
                        @if (user() && user()->is($this->sound->project->user))
                            x-on:contextmenu="volumeClear($event, {{ $i }})"
                            x-on:mousedown="volumeMousedown($event, {{ $i }})"
                            x-on:mousemove="volumeMousemove($event, {{ $i }})"
                            x-on:mouseup="stop"
                        @endif
                        class="flex h-full bg-gray-100 w-[3.125%] relative"
                    >
                        <div
                            class="absolute w-full bg-gray-200 bottom-0 pointer-events-none"
                            x-bind:style="{ height: (notes[{{ $i }}][1] * 100) + '%' }"
                        >
                            <div class="bg-red-300 h-[3px] w-full">
                                &nbsp;
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
            <div class="flex flex-col w-full">
                <details>
                    <summary>{{ __('Preload in development (dynamic)') }}</summary>
                    <x-code-block language="js">@include('snippets.sounds.load-in-dev')</x-code-block>
                </details>
                <details>
                    <summary>{{ __('Load in production (static)') }}</summary>
                    <x-code-block language="js">@include('snippets.sounds.load-in-prod')</x-code-block>
                </details>
                <details>
                    <summary>{{ __('Use in code') }}</summary>
                    <x-code-block language="js">@include('snippets.sounds.use')</x-code-block>
                </details>
            </div>
        </div>
        <div class="flex flex-col w-1/3 space-y-4">
            <div hidden class="instance"></div>
            <button
                x-on:click="play"
                class="bg-gray-100 py-3 px-2"
            >
                play
            </button>
            @if (user() && user()->is($this->sound->project->user))
                <div class="border border-gray-200 p-2 flex flex-col justify-start space-y-4">
                    <div class="flex flex-col">
                        {{ __('Slug') }}:
                        <input
                            type="text"
                            value="{{ $this->sound->slug }}"
                            wire:keyup="changeSlug($event.target.value)"
                        >
                        @if(!empty($this->errors['slug']))
                            <div class="text-red">{{ $this->errors['slug'] }}</div>
                        @endif
                    </div>
                </div>
                <div class="border border-gray-200 p-2 flex flex-col justify-start space-y-4">
                    <div class="flex flex-col">
                        <button
                            x-on:click="instrument = 0"
                            x-bind:class="{'bg-red-300': instrument == 0}"
                        >sine</button>
                        <button
                            x-on:click="instrument = 1"
                            x-bind:class="{'bg-green-300': instrument == 1}"
                        >square</button>
                        <button
                            x-on:click="instrument = 2"
                            x-bind:class="{'bg-purple-300': instrument == 2}"
                        >sawtooth</button>
                        <button
                            x-on:click="instrument = 3"
                            x-bind:class="{'bg-blue-300': instrument == 3}"
                        >triangle</button>
                    </div>
                </div>
                <div class="border border-gray-200 p-2 flex flex-col justify-start space-y-4">
                    <div class="flex flex-col">
                        {{ __('Note Length') }}:
                        <input
                            type="number"
                            value="{{ $this->length }}"
                            wire:change="changeLength($event.target.value)"
                        >
                        @if(!empty($this->errors['length']))
                            <div class="text-red">{{ $this->errors['length'] }}</div>
                        @endif
                    </div>
                </div>
                <div class="border border-gray-200 p-2 flex flex-col justify-start space-y-4">
                    <div class="flex flex-col">
                        {{ __('Snapping') }}:
                        <label>
                            <input
                                type="checkbox"
                                x-model="snapToNotes"
                            >
                            {{ __('Snap To Notes') }}
                        </label>
                        <label>
                            <input
                                type="checkbox"
                                x-model="snapToSharps"
                            >
                            {{ __('Enable Sharp Notes') }}
                        </label>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@extends('admin.layouts.tournament')

@section('title', 'Edit Peserta | ' . $tournament->name)

@section('page-label', 'MANAJEMEN PESERTA')
@section('page-title', 'Edit Peserta')
@section('page-subtitle', $tournament->name)

@section('content')
    <div class="p-4 sm:p-6 max-w-3xl">

        @if($errors->any())
            <div class="mb-6 rounded-xl bg-rose-900/20 border border-rose-500/20 p-4 text-rose-200">
                <p class="font-semibold mb-2">Terdapat kesalahan validasi:</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('tournaments.participants.update', [$tournament, $participant]) }}" method="POST" enctype="multipart/form-data" class="space-y-6 bg-slate-900/80 border border-slate-800 rounded-3xl p-6" id="participantForm">
            @csrf
            @method('PUT')

            <div class="grid gap-6">
                <!-- Nama Tim -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Nama Tim <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $participant->team->name) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('name') border-red-500 @enderror" required>
                    @error('name')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Logo Tim -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Logo Tim (JPG, JPEG, PNG, WebP)</label>
                    <input type="file" accept="image/jpeg,image/jpg,image/png,image/webp" name="logo" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('logo') border-red-500 @enderror">
                    <p class="text-slate-400 text-xs mt-1">Maksimal 2MB. Kosongkan jika tidak ingin mengganti logo.</p>
                    @if($participant->team->logo)
                        <div class="mt-3">
                            <p class="text-sm text-slate-300 mb-2">Preview logo saat ini:</p>
                            <img src="{{ asset('storage/' . $participant->team->logo) }}" alt="logo" class="h-24 w-auto rounded-md border border-slate-800">
                        </div>
                    @endif
                    @error('logo')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Negara -->
                <div>
                    <label class="block text-sm font-semibold text-slate-300 mb-2">Negara <span class="text-red-500">*</span></label>
                    <select name="country" id="country" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('country') border-red-500 @enderror" required>
                        <option value="">-- Pilih Negara --</option>
                    </select>
                    @error('country')
                        <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Indonesia: Provinsi & Kota -->
                <div id="indonesian-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-6" style="display: none;">
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Provinsi <span class="text-red-500">*</span></label>
                        <select id="province" name="province" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('province') border-red-500 @enderror">
                            <option value="">-- Pilih Provinsi --</option>
                        </select>
                        @error('province')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Kota / Kabupaten <span class="text-red-500">*</span></label>
                        <select id="city" name="city" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('city') border-red-500 @enderror">
                            <option value="">-- Pilih Kota --</option>
                        </select>
                        @error('city')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Non-Indonesia: State & City -->
                <div id="non-indonesian-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-6" style="display: none;">
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">State / Province <span class="text-red-500">*</span></label>
                        <input type="text" id="state" name="state" value="{{ old('state') }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('state') border-red-500 @enderror">
                        @error('state')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">City <span class="text-red-500">*</span></label>
                        <input type="text" id="non-indo-city" name="city" value="{{ old('city', $participant->team->city) }}" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-white focus:border-indigo-500 focus:outline-none @error('city') border-red-500 @enderror">
                        @error('city')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-3">
                <a href="{{ route('tournaments.participants.index', $tournament) }}" class="px-5 py-3 bg-slate-800 border border-slate-700 rounded-xl text-slate-300 hover:bg-slate-700 transition">Batal</a>
                <button type="submit" class="px-5 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl text-white font-semibold transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const indonesianProvinces = @json(\App\Helpers\LocationData::getIndonesianProvinces());
        const countries = @json(\App\Helpers\LocationData::getCountries());

        function populateCountries() {
            const countryEl = document.getElementById('country');
            countries.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                if (c === '{{ old('country', $participant->team->country ?? 'Indonesia') }}') opt.selected = true;
                countryEl.appendChild(opt);
            });
        }

        function populateProvinces() {
            const provEl = document.getElementById('province');
            Object.keys(indonesianProvinces).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p;
                opt.textContent = p;
                provEl.appendChild(opt);
            });
        }

        function onCountryChange() {
            const country = document.getElementById('country').value;
            const indoFields = document.getElementById('indonesian-fields');
            const nonIndoFields = document.getElementById('non-indonesian-fields');
            const provinceEl = document.getElementById('province');
            const cityEl = document.getElementById('city');
            const nonIndoCityEl = document.getElementById('non-indo-city');
            const stateEl = document.getElementById('state');

            if (country === 'Indonesia') {
                indoFields.style.display = 'grid';
                nonIndoFields.style.display = 'none';

                provinceEl.required = true;
                cityEl.required = true;
                stateEl.required = false;
                nonIndoCityEl.required = false;

                provinceEl.disabled = false;
                cityEl.disabled = false;
                cityEl.name = 'city';
                nonIndoCityEl.disabled = true;
                nonIndoCityEl.name = '';
                nonIndoCityEl.value = '';
            } else if (country) {
                indoFields.style.display = 'none';
                nonIndoFields.style.display = 'grid';

                provinceEl.required = false;
                cityEl.required = false;
                stateEl.required = true;
                nonIndoCityEl.required = true;

                provinceEl.disabled = true;
                provinceEl.value = '';
                cityEl.disabled = true;
                cityEl.name = '';
                cityEl.value = '';
                nonIndoCityEl.disabled = false;
                nonIndoCityEl.name = 'city';
            } else {
                indoFields.style.display = 'none';
                nonIndoFields.style.display = 'none';

                provinceEl.disabled = true;
                cityEl.disabled = true;
                cityEl.name = '';
                nonIndoCityEl.disabled = true;
                nonIndoCityEl.name = '';
            }
        }

        function onProvinceChange() {
            const prov = document.getElementById('province').value;
            const cityEl = document.getElementById('city');
            cityEl.innerHTML = '<option value="">-- Pilih Kota --</option>';
            if (!prov || !indonesianProvinces[prov]) return;
            indonesianProvinces[prov].forEach(ct => {
                const opt = document.createElement('option');
                opt.value = ct;
                opt.textContent = ct;
                if (ct === '{{ old('city', $participant->team->city) }}') opt.selected = true;
                cityEl.appendChild(opt);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            populateCountries();
            populateProvinces();
            document.getElementById('country').addEventListener('change', onCountryChange);
            document.getElementById('province').addEventListener('change', onProvinceChange);

            // Initialize based on current country
            onCountryChange();
            
            // If Indonesia, try to find and select the current city's province
            if (document.getElementById('country').value === 'Indonesia') {
                const currentCity = '{{ old('city', $participant->team->city ?? '') }}';
                if (currentCity) {
                    for (const p of Object.keys(indonesianProvinces)) {
                        if (indonesianProvinces[p].includes(currentCity)) {
                            document.getElementById('province').value = p;
                            onProvinceChange();
                            document.getElementById('city').value = currentCity;
                            break;
                        }
                    }
                }
            }

            // Form validation before submit
            document.getElementById('participantForm').addEventListener('submit', (e) => {
                const country = document.getElementById('country').value.trim();
                const city = document.getElementById('city').value.trim();
                const nonIndoCity = document.getElementById('non-indo-city').value.trim();

                // Validate country is selected
                if (!country) {
                    e.preventDefault();
                    alert('Negara wajib dipilih');
                    return false;
                }

                if (country === 'Indonesia') {
                    const province = document.getElementById('province').value.trim();
                    
                    // Validate province is selected
                    if (!province) {
                        e.preventDefault();
                        alert('Provinsi wajib dipilih untuk Indonesia');
                        return false;
                    }

                    // Validate city is selected
                    if (!city) {
                        e.preventDefault();
                        alert('Kota/Kabupaten wajib dipilih untuk Indonesia');
                        return false;
                    }

                    // Clear non-Indonesia fields before submit
                    document.getElementById('state').value = '';
                    document.getElementById('non-indo-city').value = '';
                } else {
                    const state = document.getElementById('state').value.trim();

                    // Validate state and city for non-Indonesia
                    if (!state) {
                        e.preventDefault();
                        alert('State/Province wajib diisi');
                        return false;
                    }

                    if (!nonIndoCity) {
                        e.preventDefault();
                        alert('City wajib diisi');
                        return false;
                    }

                    // Clear Indonesia fields before submit
                    document.getElementById('province').value = '';
                    document.getElementById('city').value = '';
                }
            });
        });
    </script>
@endpush

@extends('layouts.master2')


@section('title', 'Tambah Jenis Analisa - PT.Evo Nusa Bersaudara')

@section('content')
    @push('css')
        <style>
            .divider {
                height: 2px;
                background: linear-gradient(90deg,
                        rgba(13, 110, 253, 0.1) 0%,
                        rgba(13, 110, 253, 0.5) 50%,
                        rgba(13, 110, 253, 0.1) 100%);
            }
        </style>
        <style>
            .calculator-container {
                max-width: 400px;
                margin: 0 auto;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }

            .calculator-header {
                background-color: #0d6efd;
                color: white;
                padding: 15px;
                text-align: center;
            }

            .calculator-display {
                background-color: #f8f9fa;
                padding: 15px;
                text-align: right;
            }

            #calculator-display {
                width: 100%;
                border: none;
                background: transparent;
                font-size: 2rem;
                font-weight: bold;
                text-align: right;
            }

            .formula-display {
                min-height: 20px;
                color: #6c757d;
                font-size: 0.9rem;
            }

            .calculator-buttons {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                padding: 15px;
                background-color: #f1f3f5;
            }

            .btn-calculator {
                border: none;
                border-radius: 8px;
                padding: 15px 0;
                font-size: 1.2rem;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-calculator:hover {
                filter: brightness(0.95);
            }

            .btn-number {
                background-color: white;
                color: #212529;
            }

            .btn-operator {
                background-color: #e9ecef;
                color: #0d6efd;
            }

            .btn-equals {
                background-color: #0d6efd;
                color: white;
            }

            .btn-clear,
            .btn-backspace {
                background-color: #f8f9fa;
                color: #dc3545;
            }

            .span-2 {
                grid-column: span 2;
            }

            .parameter-section {
                padding: 15px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }

            .parameter-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-top: 10px;
            }

            .parameter-btn {
                background-color: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 10px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s;
            }

            .parameter-btn:hover {
                background-color: #f1f3f5;
            }

            .result-section {
                padding: 15px;
                background-color: white;
                border-top: 1px solid #dee2e6;
            }
        </style>
    @endpush


    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Jenis Analisa Pada LAB PT. EVO MANUFACTURING INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <form action="{{ route('jenisanalisa.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="Kode_Analisa" class="form-label fw-semibold">Kode Analisa <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('Kode_Analisa') is-invalid shake @enderror"
                                    name="Kode_Analisa" id="Kode_Analisa" value="{{ old('Kode_Analisa') }}"
                                    placeholder="Masukkan Kode Analisa">
                                @error('Kode_Analisa')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="Jenis_Analisa" class="form-label fw-semibold">Jenis Analisa <span
                                        class="text-danger">*</span></label>
                                <input type="text"
                                    class="form-control @error('Jenis_Analisa') is-invalid shake @enderror"
                                    name="Jenis_Analisa" id="Jenis_Analisa" value="{{ old('Jenis_Analisa') }}"
                                    placeholder="Masukkan Jenis Analisa">
                                @error('Jenis_Analisa')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="Id_Mesin" class="form-label fw-semibold">Nama Mesin <span
                                        class="text-danger">*</span></label>
                                <select
                                    class="js-example-basic-single form-control @error('Id_Mesin') is-invalid shake @enderror"
                                    name="Id_Mesin" id="Id_Mesin" data-placeholder="-- Pilih Mesin --">
                                    <option value=""></option>
                                    @foreach ($mesin as $i)
                                        <option value="{{ $i->Id_Master_Mesin }}"
                                            {{ old('Id_Mesin') == $i->Id_Master_Mesin ? 'selected' : '' }}>
                                            {{ $i->Nama_Mesin ?? 'Tidak Ada Data' }} ~ {{ $i->Seri_Mesin ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('Id_Mesin')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="Flag_Perhitungan" class="form-label fw-semibold">Apakah Analisis Ini Memakai
                                    Perhitungan Berdasarkan Validasi ? jika Ya mohon centang Ya <span
                                        class="text-danger">*</span></label>
                                <div class="d-flex gap-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="Flag_Perhitungan"
                                            value="Y" id="flexRadioDefault1">
                                        <label class="form-check-label" for="flexRadioDefault1">
                                            YA
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="Flag_Perhitungan"
                                            id="flexRadioDefault2" value="">
                                        <label class="form-check-label" for="flexRadioDefault2">
                                            TIDAK
                                        </label>
                                    </div>
                                </div>

                                @error('Flag_Perhitungan')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 d-grid">
                                <button type="submit" class="btn btn-success">Submit</button>
                            </div>
                        </form>

                    </div>

                    <div class="col-md-6">
                        <div class="calculator-container">
                            <div class="calculator-header">
                                <h3><i class="fas fa-calculator"></i> Kalkulator Analitik</h3>
                            </div>

                            <!-- Parameter Section -->
                            <div class="parameter-section">
                                <h5>Parameter Cepat</h5>
                                <div class="parameter-grid">
                                    <div class="parameter-btn" data-value="10">
                                        <small>Parameter 1</small>
                                        <div class="fw-bold">10</div>
                                    </div>
                                    <div class="parameter-btn" data-value="20">
                                        <small>Parameter 2</small>
                                        <div class="fw-bold">20</div>
                                    </div>
                                    <div class="parameter-btn" data-value="30">
                                        <small>Parameter 3</small>
                                        <div class="fw-bold">30</div>
                                    </div>
                                    <div class="parameter-btn" data-value="40">
                                        <small>Parameter 4</small>
                                        <div class="fw-bold">40</div>
                                    </div>
                                    <div class="parameter-btn" data-value="50">
                                        <small>Parameter 5</small>
                                        <div class="fw-bold">50</div>
                                    </div>
                                    <div class="parameter-btn" data-value="60">
                                        <small>Parameter 6</small>
                                        <div class="fw-bold">60</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Display -->
                            <div class="calculator-display">
                                <div class="formula-display" id="formula-text"></div>
                                <input type="text" id="calculator-display" value="0" readonly>
                            </div>

                            <!-- Calculator Buttons -->
                            <div class="calculator-buttons">
                                <button class="btn-calculator btn-clear" value="C">C</button>
                                <button class="btn-calculator btn-backspace"><i class="fas fa-backspace"></i></button>
                                <button class="btn-calculator btn-operator" value="%">%</button>
                                <button class="btn-calculator btn-operator" value="/">÷</button>

                                <button class="btn-calculator btn-number" value="7">7</button>
                                <button class="btn-calculator btn-number" value="8">8</button>
                                <button class="btn-calculator btn-number" value="9">9</button>
                                <button class="btn-calculator btn-operator" value="*">×</button>

                                <button class="btn-calculator btn-number" value="4">4</button>
                                <button class="btn-calculator btn-number" value="5">5</button>
                                <button class="btn-calculator btn-number" value="6">6</button>
                                <button class="btn-calculator btn-operator" value="-">-</button>

                                <button class="btn-calculator btn-number" value="1">1</button>
                                <button class="btn-calculator btn-number" value="2">2</button>
                                <button class="btn-calculator btn-number" value="3">3</button>
                                <button class="btn-calculator btn-operator" value="+">+</button>

                                <button class="btn-calculator btn-number span-2" value="0">0</button>
                                <button class="btn-calculator btn-number" value=".">.</button>
                                <button class="btn-calculator btn-equals">=</button>
                            </div>

                            <!-- Result Section -->
                            <div class="result-section">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h6>Rumus Lengkap:</h6>
                                        <div id="full-formula" class="fw-bold">-</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6>Hasil:</h6>
                                        <div id="final-result" class="fw-bold">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const display = document.getElementById('calculator-display');
            const formulaText = document.getElementById('formula-text');
            const fullFormula = document.getElementById('full-formula');
            const finalResult = document.getElementById('final-result');
            let currentInput = '0';
            let previousInput = '';
            let operation = null;
            let resetScreen = false;
            let calculationHistory = [];

            // Number buttons
            document.querySelectorAll('.btn-number').forEach(button => {
                button.addEventListener('click', () => {
                    appendNumber(button.value);
                });
            });

            // Parameter buttons
            document.querySelectorAll('.parameter-btn').forEach(button => {
                button.addEventListener('click', () => {
                    appendNumber(button.getAttribute('data-value'));
                });
            });

            // Operator buttons
            document.querySelectorAll('.btn-operator').forEach(button => {
                button.addEventListener('click', () => {
                    chooseOperation(button.value);
                });
            });

            // Equals button
            document.querySelector('.btn-equals').addEventListener('click', () => {
                if (operation === null) return;
                calculate();
                showFinalResult();
            });

            // Clear button
            document.querySelector('.btn-clear').addEventListener('click', () => {
                clearCalculator();
            });

            // Backspace button
            document.querySelector('.btn-backspace').addEventListener('click', () => {
                backspace();
            });

            function appendNumber(number) {
                if (currentInput === '0' || resetScreen) {
                    currentInput = number;
                    resetScreen = false;
                } else {
                    if (number === '.' && currentInput.includes('.')) return;
                    currentInput += number;
                }
                updateDisplay();
            }

            function chooseOperation(op) {
                if (currentInput === '') return;

                if (previousInput !== '') {
                    calculate();
                }

                const operatorMap = {
                    '+': '+',
                    '-': '-',
                    '*': '×',
                    '/': '÷',
                    '%': '%'
                };

                operation = op;
                previousInput = currentInput;
                calculationHistory.push(currentInput, operatorMap[operation]);
                currentInput = '';

                updateDisplay();
            }

            function calculate() {
                let result;
                const prev = parseFloat(previousInput);
                const current = parseFloat(currentInput);

                if (isNaN(prev) || isNaN(current)) return;

                switch (operation) {
                    case '+':
                        result = prev + current;
                        break;
                    case '-':
                        result = prev - current;
                        break;
                    case '*':
                        result = prev * current;
                        break;
                    case '/':
                        result = prev / current;
                        break;
                    case '%':
                        result = prev % current;
                        break;
                    default:
                        return;
                }

                currentInput = result.toString();
                operation = null;
                previousInput = '';
                resetScreen = true;
                updateDisplay();
            }

            function backspace() {
                if (currentInput.length === 1 || (currentInput.length === 2 && currentInput.startsWith('-'))) {
                    currentInput = '0';
                } else {
                    currentInput = currentInput.slice(0, -1);
                }
                updateDisplay();
            }

            function showFinalResult() {
                let formula = calculationHistory.join(' ') + ' ' + currentInput;
                fullFormula.textContent = formula + ' =';
                finalResult.textContent = currentInput;
                calculationHistory = [];
            }

            function clearCalculator() {
                currentInput = '0';
                previousInput = '';
                operation = null;
                calculationHistory = [];
                fullFormula.textContent = '-';
                finalResult.textContent = '-';
                updateDisplay();
            }

            function updateDisplay() {
                let displayValue = currentInput;
                const parts = displayValue.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                display.value = parts.join('.');

                formulaText.textContent = calculationHistory.join(' ') + (operation ? ' ' + currentInput : '');
            }
        });
    </script>

    @if (session('success'))
        <script>
            Swal.fire({
                title: "Berhasil",
                text: "{{ session('success') }}",
                icon: "success"
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                title: "Ops...",
                text: "{{ session('error') }}",
                icon: "error"
            });
        </script>
    @endif
    @push('js')
        <script>
            $(document).ready(function() {
                $('.js-example-basic-single').select2({
                    placeholder: $(this).data('placeholder'),
                    allowClear: true

                });
            });
        </script>
    @endpush
@endsection

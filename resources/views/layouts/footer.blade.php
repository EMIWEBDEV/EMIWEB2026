  <div class="modal fade" id="gantiPasswordModal{{ Session::get('user.id') }}" tabindex="-1"
      aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header bg-light p-3">
                  <h5 class="modal-title" id="exampleModalLabel">Ganti Password</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                      id="close-modal"></button>
              </div>
              <form method="POST" action="{{ route('gantipassword.store') }}">
                  @csrf
                  <div class="modal-body">
                    <input type="hidden" name="username" value="{{ Session::get('user.username') }}">
                      <div class="mb-3" id="modal-id">
                          <label class="form-label">Password Lama</label>
                          <input type="password" name="PasswordLama" id="id-field-lama" class="form-control"
                              placeholder="Password Lama" required />
                      </div>
                      <div class="mb-3" id="modal-id">
                          <label class="form-label">Password Baru</label>
                          <input type="password" id="id-field" name="Password" class="form-control"
                              placeholder="Password Lama" required />
                              <div id="password-feedback" class="form-text text-danger mt-1"></div>
                      </div>
                  </div>
                  <div class="modal-footer">
                      <div class="hstack gap-2 justify-content-end">
                          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-success" id="add-btnpins">Ganti Password</button>
                      </div>
                  </div>
              </form>
          </div>
      </div>
  </div>

  <div class="modal fade zoomIn" id="logoutModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                      id="btn-close"></button>
              </div>
              <div class="modal-body">
                  <form id="logout-form" action="{{ url('/logout') }}" method="POST">
                      @csrf
                      <div class="mt-2 text-center">
                          <img src="{{ URL::asset('assets/images/goodbye2.gif') }}" style="width:auto;height:100%">
                          <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                              <h4>Ingin Logout ?</h4>
                          </div>
                      </div>
                      <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                          <a type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Tidak</a>
                          <button type="submit" class="btn w-sm btn-danger ">Ya !</button>
                      </div>
                  </form>
              </div>
          </div>
      </div>
  </div>


  <div class="modal fade" id="gantiPin{{ Session::get('user.id') }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
          <div class="modal-header bg-light p-3">
              <h5 class="modal-title" id="exampleModalLabel">Ganti PIN</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                  id="close-modal"></button>
          </div>
          <form method="POST" action="{{ route('gantiPin.store') }}">
              @csrf
             <div class="modal-body"> 
    <input type="hidden" name="username" value="{{ Session::get('user.username') }}">

    <div class="mb-3">
        <label class="form-label">PIN Lama</label>
        <input type="password"
            name="PinLama"
            class="form-control"
            placeholder="PIN Lama"
            maxlength="6"
            inputmode="numeric"
            pattern="\d{6}"
            required />
    </div>

    <div class="mb-3">
        <label class="form-label">PIN Baru</label>
        <small class="text-danger">PIN harus 6 digit angka</small>
        <input type="password"
            name="Pin"
            class="form-control"
            placeholder="PIN Baru 6 Digit"
            maxlength="6"
            inputmode="numeric"
            pattern="\d{6}"
            required />
    </div>
</div>

              <div class="modal-footer">
                  <div class="hstack gap-2 justify-content-end">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                      <button type="submit" class="btn btn-success" id="add-btnpin">Ganti PIN</button>
                  </div>
              </div>
          </form>
      </div>
  </div>
</div>

  <footer class="footer">
      <div class="container-fluid">
          <div class="row">
              <div class="col-sm-6">
                  <a>{{ date('Y') }}</a> © EMI LAB Versi 2.2.0
              </div>
              <div class="col-sm-6">
                  <div class="text-sm-end d-none d-sm-block">
                     
                  </div>
              </div>
          </div>
      </div>
  </footer>



@if(session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: "{{ session('success') }}",
            showConfirmButton: true
        });
    </script>
@elseif(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "{{ session('error') }}",
            showConfirmButton: true
        });
    </script>
@endif

<script>
document.querySelectorAll('input[name="Pin"], input[name="PinLama"]').forEach(input => {
    input.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6); // hanya angka, max 6 digit
    });
});
</script>

<script>
    const passwordInput = document.getElementById("id-field");
    const feedback = document.getElementById("password-feedback");

    passwordInput.addEventListener("input", function () {
        const value = passwordInput.value;
        let messages = [];

        // Minimal 6 karakter
        if (value.length < 6) {
            messages.push("Minimal 6 karakter");
        }

        // Mengandung huruf
        if (!/[a-zA-Z]/.test(value)) {
            messages.push("Harus mengandung huruf (a-z atau A-Z)");
        }

        // Mengandung angka
        if (!/[0-9]/.test(value)) {
            messages.push("Harus mengandung angka (0-9)");
        }

        // Mengandung simbol
        if (!/[\W_]/.test(value)) {
            messages.push("Harus mengandung simbol (contoh: !@#$%)");
        }

        // Tampilkan pesan
        if (messages.length > 0) {
            feedback.innerHTML = "⚠️ " + messages.join("<br>");
            feedback.classList.remove("text-success");
            feedback.classList.add("text-danger");
        } else {
            feedback.innerHTML = "✅ Password memenuhi semua syarat";
            feedback.classList.remove("text-danger");
            feedback.classList.add("text-success");
        }
    });
</script>

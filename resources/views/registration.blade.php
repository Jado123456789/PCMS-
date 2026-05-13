<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <title>
    NIYO_7 | Registration
  </title>
  <!--     Fonts and icons     -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
  <!-- Nucleo Icons -->
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <!-- CSS Files -->
  <link id="pagestyle" href="../assets/css/argon-dashboard.min.css?v=2.0.4" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  @php
    $viteAssetsReady = file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));
  @endphp
  @if ($viteAssetsReady)
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @endif

</head>

<body class="">
  <main class="main-content  mt-0">
    <section>
      <div class="page-header min-vh-100">
        <div class="container">
          <div class="row">
            <div class="col-xl-4 col-lg-5 col-md-7 d-flex flex-column mx-lg-0 mx-auto">
              <div class="card card-plain">
                <div class="card-header pb-0 text-start">
                  <h4 class="font-weight-bolder">Sign Up</h4>
                  <p class="mb-0">Enter your details to register</p>
                </div>
                <div class="card-body">
                  @php
                    $registrationOtp = session('registration_otp');
                    $emailVerified = old('email') && $registrationOtp && ($registrationOtp['email'] ?? null) === old('email') && ($registrationOtp['verified'] ?? false);
                  @endphp
                  @if($errors->any())
                      <div class="text-danger mb-3">
                        @foreach($errors->all() as $error)
                          <div>{{ $error }}</div>
                        @endforeach
                      </div>
                  @endif
                  <form role="form" method="POST" action="{{ route('register.submit') }}" class="text-start">
                    @csrf
                    <div class="mb-3">
                      <input type="text" class="form-control form-control-lg" name="name" placeholder="Full Name" aria-label="Name" value="{{ old('name') }}">
                    </div>
                    @error('name')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror

                    <div class="mb-3">
                      <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Email" aria-label="Email" value="{{ old('email') }}">
                    </div>
                    @error('email')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                    <div class="d-grid mb-3">
                      <button type="button" id="generateOtpButton" class="btn btn-outline-primary mb-0">Generate OTP</button>
                    </div>
                    <div id="otpStatus" class="small mb-3 {{ $emailVerified ? 'text-success' : 'text-muted' }}">
                      {{ $emailVerified ? 'Email verified. You can now set your password.' : 'Generate an OTP and verify your email before creating a password.' }}
                    </div>
                    <div class="mb-3">
                      <input type="text" class="form-control form-control-lg" id="verification_code" placeholder="Verification Code" aria-label="Verification Code" maxlength="6">
                    </div>
                    <div class="d-grid mb-3">
                      <button type="button" id="verifyOtpButton" class="btn btn-outline-success mb-0">Verify Code</button>
                    </div>
                    <div id="otpError" class="text-danger mb-3"></div>

                    <div class="mb-3">
                      <input type="tel" class="form-control form-control-lg" name="telephone" placeholder="Phone Number" aria-label="Telephone" value="{{ old('telephone') }}">
                    </div>
                    @error('telephone')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror

                    <div class="mb-3">
                      <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Password (Min 8 characters)" aria-label="Password" {{ $emailVerified ? '' : 'disabled' }}>
                    </div>
                    @error('password')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror

                    <div class="mb-3">
                      <input type="password" class="form-control form-control-lg" id="password_confirmation" name="password_confirmation" placeholder="Confirm Password" aria-label="Confirm Password" {{ $emailVerified ? '' : 'disabled' }}>
                      <small id="passwordMatch" class="d-block mt-1"></small>
                    </div>
                    @error('password_confirmation')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror

                    <div class="text-center">
                      <button type="submit" id="registerButton" class="btn btn-lg btn-primary btn-lg w-100 mt-4 mb-0" {{ $emailVerified ? '' : 'disabled' }}>Sign up</button>
                    </div>
                  </form>
                </div>
                <div class="card-footer text-center pt-0 px-lg-2 px-1">
                  <p class="mb-4 text-sm mx-auto">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-primary text-gradient font-weight-bold">Sign in</a>
                  </p>
                </div>
              </div>
            </div>
            <div class="col-6 d-lg-flex d-none h-100 my-auto pe-0 position-absolute top-0 end-0 text-center justify-content-center flex-column">
              <div class="position-relative h-100 m-3 px-7 border-radius-lg d-flex flex-column justify-content-center overflow-hidden" style="background: linear-gradient(135deg, #1565c0 0%, #0d47a1 50%, #1976d2 100%); background-image: url('https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&h=800&fit=crop'); background-size: cover; background-position: center; background-blend-mode: overlay;">

                <span class="mask bg-gradient-primary opacity-4"></span>
                <h4 class="mt-5 text-white font-weight-bolder position-relative">"Join the Energy Revolution"</h4>
                <p class="text-white position-relative">Create an account to start monitoring your electricity usage in real-time with our smart meter system.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
  <!--   Core JS Files   -->
  <script src="../assets/js/core/popper.min.js"></script>
  <script src="../assets/js/core/bootstrap.min.js"></script>
  <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }

    // Real-time password confirmation validation
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('password_confirmation');
    const passwordMatchMessage = document.getElementById('passwordMatch');
    const emailField = document.getElementById('email');
    const otpField = document.getElementById('verification_code');
    const generateOtpButton = document.getElementById('generateOtpButton');
    const verifyOtpButton = document.getElementById('verifyOtpButton');
    const otpStatus = document.getElementById('otpStatus');
    const otpError = document.getElementById('otpError');
    const registerButton = document.getElementById('registerButton');
    const csrfToken = document.querySelector('input[name=\"_token\"]').value;

    function setPasswordLockState(isUnlocked) {
      passwordField.disabled = !isUnlocked;
      confirmPasswordField.disabled = !isUnlocked;
      registerButton.disabled = !isUnlocked;
    }

    function resetVerificationState(message = 'Generate an OTP and verify your email before creating a password.') {
      setPasswordLockState(false);
      otpStatus.textContent = message;
      otpStatus.className = 'small mb-3 text-muted';
      otpError.textContent = '';
      passwordField.value = '';
      confirmPasswordField.value = '';
      passwordMatchMessage.textContent = '';
      confirmPasswordField.classList.remove('border-danger', 'border-success');
    }

    async function postJson(url, payload) {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw data;
      }

      return data;
    }

    emailField.addEventListener('input', () => {
      resetVerificationState();
    });

    generateOtpButton.addEventListener('click', async () => {
      const email = emailField.value.trim();

      if (!email) {
        otpError.textContent = 'Please enter your email first.';
        return;
      }

      generateOtpButton.disabled = true;
      otpError.textContent = '';

      try {
        const data = await postJson('{{ route('register.send-otp') }}', { email });
        otpStatus.textContent = data.message;
        otpStatus.className = 'small mb-3 text-success';
      } catch (error) {
        otpStatus.textContent = 'Generate an OTP and verify your email before creating a password.';
        otpStatus.className = 'small mb-3 text-muted';
        otpError.textContent = error.errors?.email?.[0] || error.message || 'Could not send OTP.';
      } finally {
        generateOtpButton.disabled = false;
      }
    });

    verifyOtpButton.addEventListener('click', async () => {
      const email = emailField.value.trim();
      const verification_code = otpField.value.trim();

      if (!email || !verification_code) {
        otpError.textContent = 'Enter your email and the verification code.';
        return;
      }

      verifyOtpButton.disabled = true;
      otpError.textContent = '';

      try {
        const data = await postJson('{{ route('register.verify-otp') }}', { email, verification_code });
        setPasswordLockState(true);
        otpStatus.textContent = data.message;
        otpStatus.className = 'small mb-3 text-success';
      } catch (error) {
        setPasswordLockState(false);
        otpError.textContent = error.errors?.verification_code?.[0] || error.message || 'Could not verify the code.';
      } finally {
        verifyOtpButton.disabled = false;
      }
    });

    function checkPasswordMatch() {
      if (!confirmPasswordField.value) {
        passwordMatchMessage.textContent = '';
        passwordMatchMessage.className = 'd-block mt-1';
        confirmPasswordField.classList.remove('border-danger', 'border-success');
        return;
      }

      const passwordsMatch = passwordField.value === confirmPasswordField.value;
      const minLength = passwordField.value.length >= 8;

      if (passwordsMatch) {
        passwordMatchMessage.textContent = '✓ Passwords match';
        passwordMatchMessage.className = 'd-block mt-1 text-success';
        confirmPasswordField.classList.remove('border-danger');
        confirmPasswordField.classList.add('border-success');
      } else {
        passwordMatchMessage.textContent = '✗ Passwords do not match';
        passwordMatchMessage.className = 'd-block mt-1 text-danger';
        confirmPasswordField.classList.remove('border-success');
        confirmPasswordField.classList.add('border-danger');
      }
    }

    if (passwordField && confirmPasswordField) {
      passwordField.addEventListener('input', checkPasswordMatch);
      confirmPasswordField.addEventListener('input', checkPasswordMatch);
    }

    setPasswordLockState({{ $emailVerified ? 'true' : 'false' }});
  </script>
  <!-- Github buttons -->
  <script async defer src="https://buttons.github.io/buttons.js"></script>
  <!-- Control Center for Soft Dashboard: parallax effects, scripts for the example pages etc -->
  <script src="../assets/js/argon-dashboard.min.js?v=2.0.4"></script>
</body>

</html>

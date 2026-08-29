<template>
    <div class="login-page">
        <div class="metal-bg metal-bg--silver"></div>
        <div class="metal-bg metal-bg--gold"></div>
        <div class="jewelry-float jewelry-float--gold" aria-hidden="true"></div>
        <div
            class="jewelry-float jewelry-float--silver"
            aria-hidden="true"
        ></div>

        <div class="login-card">
            <div class="brand-mark">
                <img
                    src="/images/logo-gold-jewelry.png"
                    alt="Ayyub Jewelers"
                    class="brand-logo"
                />
                <span class="brand-name">Ayyub Jewelers</span>
            </div>

            <div class="metal-tags" aria-hidden="true">
                <span class="metal-tag metal-tag--gold">{{ $t('login.gold') }}</span>
                <span class="metal-tag metal-tag--silver">{{ $t('login.silver') }}</span>
            </div>

            <div class="login-lang">
                <select
                    class="jewelry-lang-select"
                    :value="locale"
                    :title="$t('lang.label')"
                    @change="onLocaleChange"
                >
                    <option value="en">{{ $t('lang.english') }}</option>
                    <option value="ur">{{ $t('lang.urdu') }}</option>
                </select>
            </div>

            <h1 class="login-title">{{ $t('login.title') }}</h1>

            <form @submit.prevent="login">
                <div class="form-group">
                    <label for="login">{{ $t('login.username') }}</label>
                    <div class="input-wrap">
                        <span class="input-icon"
                            ><i class="fas fa-user"></i
                        ></span>
                        <input
                            id="login"
                            v-model="form.login"
                            type="text"
                            required
                            autocomplete="username"
                            placeholder="Email or Username"
                        />
                    </div>
                </div>

                <div class="form-group form-group--password">
                    <label for="password">{{ $t('login.password') }}</label>
                    <div class="input-wrap">
                        <span class="input-icon"
                            ><i class="fas fa-lock"></i
                        ></span>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                        />
                    </div>
                </div>

                <p v-if="error" class="login-error">{{ error }}</p>

                <button type="submit" class="login-btn" :disabled="loading">
                    {{ loading ? $t('login.signingIn') : $t('login.submit') }}
                </button>
            </form>

            <div class="login-footer">
                <p class="login-tagline">{{ $t('login.tagline') }}</p>
            </div>
        </div>
    </div>
</template>

<script>
import { setAuth, getHomeRoute } from "../auth";
import { i18nState, setLocale } from "../i18n";

export default {
    name: "Login",
            data() {
        return {
            form: {
                login: "",
                password: ""
            },
            loading: false,
            error: ""
        };
    },
    computed: {
        locale() {
            return i18nState.locale;
        },
    },
    methods: {
        onLocaleChange(event) {
            setLocale(event.target.value);
        },
        login() {
            this.loading = true;
            this.error = "";

            axios
                .post("/api/login", this.form)
                .then(res => {
                    setAuth(res.data.token, res.data.user);
                    axios.defaults.headers.common[
                        "Authorization"
                    ] = `Bearer ${res.data.token}`;

                    if (res.data.user.role === "investor") {
                        this.$router.push("/investor");
                    } else {
                        this.$router.push(getHomeRoute(res.data.user));
                    }
                })
                .catch(err => {
                    this.error =
                        err.response?.data?.message ||
                        err.response?.data?.errors?.login?.[0] ||
                        err.response?.data?.errors?.email?.[0] ||
                        "Login failed. Please check your credentials.";
                })
                .finally(() => {
                    this.loading = false;
                });
        }
    }
};
</script>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    position: relative;
    overflow: hidden;
    background: #1c1c22;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

/* Split background: silver left, gold right */
.metal-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.metal-bg--silver {
    background: linear-gradient(
        135deg,
        #2a2a32 0%,
        #3d3d48 38%,
        #5a5a66 48%,
        transparent 48%
    );
}

.metal-bg--gold {
    background: linear-gradient(
        135deg,
        transparent 0%,
        transparent 46%,
        #8b6914 46%,
        #c9a227 62%,
        #f0d875 78%,
        #b8860b 100%
    );
}

/* Decorative ingot / bar shapes */
.jewelry-float {
    position: absolute;
    border-radius: 18px;
    opacity: 0.22;
    pointer-events: none;
}

.jewelry-float--gold {
    width: 140px;
    height: 56px;
    top: 14%;
    right: 10%;
    background: linear-gradient(145deg, #fff3b0, #d4af37 40%, #8b6914);
    box-shadow: inset 0 2px 8px rgba(255, 255, 255, 0.35);
    transform: rotate(24deg);
}

.jewelry-float--silver {
    width: 120px;
    height: 48px;
    bottom: 16%;
    left: 8%;
    background: linear-gradient(145deg, #ffffff, #c8c8c8 45%, #787878);
    box-shadow: inset 0 2px 8px rgba(255, 255, 255, 0.5);
    transform: rotate(-16deg);
}

.login-card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 420px;
    background: linear-gradient(180deg, #fffdf8 0%, #ffffff 18%);
    border-radius: 24px;
    padding: 36px 42px 32px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35),
        0 0 0 1px rgba(212, 175, 55, 0.25);
    border-top: 4px solid transparent;
    border-image: linear-gradient(
            90deg,
            #c0c0c0,
            #d4af37,
            #f4e4a6,
            #d4af37,
            #c0c0c0
        )
        1;
}

.brand-mark {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.brand-logo {
    width: 88px;
    height: 88px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #d4af37;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.45),
        inset 0 0 0 2px rgba(255, 255, 255, 0.35);
    background: #2a2520;
}

.brand-name {
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    background: linear-gradient(
        90deg,
        #8b7355,
        #d4af37,
        #c0c0c0,
        #d4af37,
        #8b7355
    );
    background-size: 200% auto;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.metal-tags {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 12px;
}

.login-lang {
    display: flex;
    justify-content: center;
    margin-bottom: 16px;
}

.login-lang .jewelry-lang-select {
    height: 32px;
    padding: 0 0.75rem;
    border-radius: 999px;
    border: 1px solid rgba(212, 175, 55, 0.45);
    background: rgba(212, 175, 55, 0.12);
    color: #f0d875;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    outline: none;
}

.login-lang .jewelry-lang-select option {
    color: #1a1a1a;
    background: #fff;
}

.metal-tag {
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.metal-tag--gold {
    color: #5c4a1a;
    background: linear-gradient(145deg, #fff8dc, #d4af37 55%, #b8860b);
    box-shadow: 0 2px 8px rgba(212, 175, 55, 0.35);
}

.metal-tag--silver {
    color: #3d3d3d;
    background: linear-gradient(145deg, #ffffff, #d8d8d8 55%, #9a9a9a);
    box-shadow: 0 2px 8px rgba(160, 160, 160, 0.35);
}

.login-title {
    margin: 0 0 28px;
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    color: #2a2520;
}

.form-group {
    margin-bottom: 22px;
}

.form-group--password {
    margin-bottom: 26px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.92rem;
    font-weight: 500;
    color: #7a7060;
}

.input-wrap {
    display: flex;
    align-items: center;
    background: linear-gradient(180deg, #faf6ee 0%, #f3ede3 100%);
    border-radius: 10px;
    padding: 0 16px;
    min-height: 50px;
    border: 1px solid rgba(212, 175, 55, 0.18);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.input-wrap:focus-within {
    border-color: rgba(212, 175, 55, 0.55);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

.input-icon {
    color: #b8860b;
    font-size: 0.9rem;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.input-wrap input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 14px 12px;
    font-size: 0.95rem;
    color: #2a2520;
    outline: none;
}

.login-error {
    margin: 0 0 16px;
    font-size: 0.875rem;
    color: #dc3545;
    text-align: center;
}

/* Gold → bronze default; hover slides to silver → bright gold */
.login-btn {
    width: 100%;
    padding: 15px 24px;
    border: none;
    border-radius: 999px;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #ffffff;
    cursor: pointer;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    background-image: linear-gradient(
        90deg,
        #f4e4a6 0%,
        #d4af37 18%,
        #8b6914 50%,
        #8b6914 50%,
        #c0c0c0 72%,
        #f0f0f0 100%
    );
    background-size: 200% 100%;
    background-position: 0% 50%;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    transition: background-position 0.45s ease, transform 0.15s ease,
        opacity 0.15s ease;
}

.login-btn:hover:not(:disabled) {
    background-position: 100% 50%;
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(212, 175, 55, 0.5);
}

.login-btn:disabled {
    opacity: 0.75;
    cursor: not-allowed;
    letter-spacing: 0.06em;
    text-transform: none;
}

.login-footer {
    margin-top: 28px;
    padding-top: 16px;
    text-align: center;
    border-top: 1px solid rgba(212, 175, 55, 0.2);
}

.login-tagline {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9a9080;
}
</style>

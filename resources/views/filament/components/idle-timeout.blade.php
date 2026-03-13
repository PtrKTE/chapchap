{{--
    Composant de déconnexion automatique pour inactivité.

    Fonctionnement :
    1. Surveille l'activité utilisateur (souris, clavier, toucher, scroll)
    2. Après X minutes d'inactivité → affiche un avertissement avec compte à rebours
    3. Si l'utilisateur ne réagit pas dans les Y secondes → déconnexion automatique
    4. Le bouton "Rester connecté" réinitialise le timer

    Configuration via variables (modifiables ci-dessous) :
    - idleLimit : durée d'inactivité avant avertissement (en secondes)
    - warningDuration : durée du compte à rebours avant déconnexion (en secondes)
--}}

<div
    x-data="{
        {{-- Configuration --}}
        idleLimit: {{ config('session.lifetime', 120) * 60 - 120 }},
        warningDuration: 120,

        {{-- État interne --}}
        showWarning: false,
        countdown: 120,
        idleTimer: null,
        countdownTimer: null,
        lastActivity: Date.now(),

        {{-- Initialisation --}}
        init() {
            this.startIdleTimer()
            this.listenForActivity()
        },

        {{-- Écoute les événements d'activité utilisateur --}}
        listenForActivity() {
            const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click']
            events.forEach(event => {
                document.addEventListener(event, () => this.onActivity(), { passive: true })
            })
        },

        {{-- Appelé à chaque activité détectée --}}
        onActivity() {
            {{-- Ignore si le warning est affiché (l'utilisateur doit cliquer le bouton) --}}
            if (this.showWarning) return

            this.lastActivity = Date.now()
            this.resetIdleTimer()
        },

        {{-- Démarre/redémarre le timer d'inactivité --}}
        startIdleTimer() {
            this.idleTimer = setTimeout(() => {
                this.showWarningModal()
            }, this.idleLimit * 1000)
        },

        resetIdleTimer() {
            clearTimeout(this.idleTimer)
            this.startIdleTimer()
        },

        {{-- Affiche l'avertissement avec compte à rebours --}}
        showWarningModal() {
            this.showWarning = true
            this.countdown = this.warningDuration

            this.countdownTimer = setInterval(() => {
                this.countdown--

                if (this.countdown <= 0) {
                    this.logout()
                }
            }, 1000)
        },

        {{-- L'utilisateur clique sur Rester connecté --}}
        stayConnected() {
            this.showWarning = false
            clearInterval(this.countdownTimer)
            this.lastActivity = Date.now()
            this.startIdleTimer()

            {{-- Ping le serveur pour renouveler la session --}}
            fetch(window.location.href, {
                method: 'HEAD',
                credentials: 'same-origin',
            }).catch(() => {})
        },

        {{-- Déconnexion automatique --}}
        logout() {
            clearInterval(this.countdownTimer)
            {{-- Utilise le formulaire de logout de Filament --}}
            const logoutForm = document.querySelector('form[action*=logout]')
            if (logoutForm) {
                logoutForm.submit()
            } else {
                window.location.href = '/admin/logout'
            }
        },

        {{-- Formate le compte à rebours en MM:SS --}}
        get countdownFormatted() {
            const min = Math.floor(this.countdown / 60)
            const sec = this.countdown % 60
            return min + ':' + (sec < 10 ? '0' : '') + sec
        }
    }"
>
    {{-- Modal d'avertissement --}}
    <template x-if="showWarning">
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm"
            x-transition
        >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 max-w-md mx-4 text-center">
                {{-- Icône horloge --}}
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                    <svg class="h-7 w-7 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>

                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                    Session bientôt expirée
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Vous êtes inactif depuis un moment. Votre session va expirer automatiquement pour des raisons de sécurité.
                </p>

                {{-- Compte à rebours --}}
                <div class="mb-5">
                    <span
                        class="text-3xl font-mono font-bold"
                        :class="countdown <= 30 ? 'text-red-500' : 'text-orange-500'"
                        x-text="countdownFormatted"
                    ></span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        avant déconnexion automatique
                    </p>
                </div>

                {{-- Boutons --}}
                <div class="flex gap-3 justify-center">
                    <button
                        @click="stayConnected()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Rester connecté
                    </button>

                    <button
                        @click="logout()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-200 dark:hover:bg-gray-600 transition"
                    >
                        Se déconnecter
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

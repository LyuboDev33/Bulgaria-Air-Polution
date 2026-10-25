
<x-front-end>
    <main>
        <section class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <span class="section-kicker">
                        КАЧЕСТВО НА ВЪЗДУХА В БЪЛГАРИЯ
                    </span>

                    <h1>
                        Къде е най-чистият въздух?
                    </h1>

                    <p>
                        Сравнете средните стойности на фините прахови
                        частици PM2.5 в София, Пловдив, Варна,
                        Бургас и Русе за избран период.
                    </p>
                </div>
            </div>
        </section>

        <section class="comparison-section">
            <div class="container">

                <div class="filter-card">
                    <div class="filter-heading">
                        <span class="section-kicker">
                            ИЗБЕРЕТЕ ПЕРИОД
                        </span>

                        <h2>Сравнете качеството на въздуха</h2>

                        <p>
                            Изберете начална и крайна дата,
                            за да видите кой град е имал
                            най-ниска средна концентрация на PM2.5.
                        </p>
                    </div>

                    <form
                        id="airQualityForm"
                        class="filter-form"
                        method="GET"
                        action="{{ route('air-quality.filter') }}"
                    >
                        <div class="form-group">
                            <label for="dateFrom">
                                Начална дата
                            </label>

                            <input
                                type="date"
                                id="dateFrom"
                                name="start_date"
                                value="{{ $filters['start_date'] }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="dateTo">
                                Крайна дата
                            </label>

                            <input
                                type="date"
                                id="dateTo"
                                name="end_date"
                                value="{{ $filters['end_date'] }}"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                            id="filterButton"
                        >
                            Сравни градовете
                        </button>
                    </form>

                    <div
                        id="formError"
                        class="form-error"
                        role="alert"
                        hidden
                    ></div>
                </div>

                {{-- Results are rendered once, inside this container. --}}
                <div id="airQualityResults">
                    @include('Frontend.components.air-quality-results', [
                        'data' => $data,
                        'winner' => $winner,
                        'filters' => $filters,
                    ])
                </div>

            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('airQualityForm');
            const results = document.getElementById('airQualityResults');
            const errorBox = document.getElementById('formError');
            const button = document.getElementById('filterButton');

            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                errorBox.hidden = true;
                errorBox.textContent = '';

                const formData = new FormData(form);
                const params = new URLSearchParams(formData);

                button.disabled = true;

                try {
                    const response = await fetch(
                        form.action + '?' + params.toString(),
                        {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }
                    );

                    if (!response.ok) {
                        throw new Error(
                            'Моля въведете валидна дата на старт и приключване.'
                        );
                    }

                    const json = await response.json();

                    results.innerHTML = json.html;

                    const pageUrl = new URL(window.location.pathname, window.location.origin);

                    pageUrl.search = params.toString();

                    window.history.pushState({},'', pageUrl.toString());

                } catch (error) {
                    errorBox.textContent = error.message;
                    errorBox.hidden = false;
                } finally {
                    button.disabled = false;
                }
            });
        });
    </script>
</x-front-end>

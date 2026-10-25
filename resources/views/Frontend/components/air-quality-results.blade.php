
@if ($data->isNotEmpty())

    <div class="results-section" id="resultsSection">

        <div class="results-header">
            <div>
                <span class="section-kicker">
                    РЕЗУЛТАТИ ОТ СРАВНЕНИЕТО
                </span>

                <h2>Къде въздухът е най-чист?</h2>

                <p id="resultsPeriod">
                    {{ $filters['start_date'] }}
                    –
                    {{ $filters['end_date'] }}
                </p>
            </div>
        </div>

        <div class="results-grid">

            <article class="winner-card">

                <div class="winner-top">
                    <span class="winner-label">
                        <span>✦</span>
                        НАЙ-ЧИСТ ВЪЗДУХ
                    </span>

                    <span class="winner-trophy">✳</span>
                </div>

                <div class="winner-content">

                    <p class="winner-intro">
                        Градът с най-чист въздух е
                    </p>

                    <h3 id="winnerCity">
                        {{ $winner['city'] }}
                    </h3>

                    <div class="winner-measurement">

                        <strong id="winnerValue">
                            {{ $winner['average_pm25'] }}
                        </strong>

                        <span>
                            µg/m³
                            Средна стойност на PM2.5
                        </span>

                    </div>

                    <div class="winner-divider"></div>

                    <p class="winner-description">
                        Този град има най-ниска средна концентрация
                        на фини прахови частици PM2.5 сред
                        сравняваните градове за избрания период.
                    </p>

                </div>

                <div class="winner-decoration"></div>

            </article>

            <article class="ranking-card">

                <div class="ranking-heading">
                    <div>
                        <h3>Класация на градовете</h3>

                        <p>
                            Колкото по-ниска е стойността на PM2.5,
                            толкова по-чист е въздухът.
                        </p>
                    </div>

                    <span class="ranking-count">
                        {{ $data->count() }} ГРАДА
                    </span>
                </div>

                <div class="ranking-list" id="rankingList">

                    @foreach ($data as $city)

                        <div class="ranking-row">

                            <span class="rank-number {{ $loop->first ? 'first' : '' }}">
                                {{ str_pad($city['rank'], 2, '0', STR_PAD_LEFT) }}
                            </span>

                            <span class="rank-city">
                                {{ $city['city'] }}
                            </span>

                            <div class="rank-bar-track">
                                <div
                                    class="rank-bar"
                                    style="width: {{ $city['bar_width'] }}%"
                                ></div>
                            </div>

                            <span class="rank-value">
                                {{ $city['average_pm25'] }}
                                <small>µg/m³</small>
                            </span>

                        </div>

                    @endforeach

                </div>

                <p class="ranking-footnote">
                    Стойностите показват средната концентрация
                    на фини прахови частици PM2.5 във въздуха,
                    измерена в микрограма на кубичен метър (µg/m³).
                </p>

            </article>

        </div>
    </div>

@else

    <div class="results-section">
        <div class="results-header">
            <div>
                <h2>Няма намерени данни</h2>

                <p>
                    Няма налични измервания за избрания период.
                    Моля, изберете други дати.
                </p>
            </div>
        </div>
    </div>

@endif

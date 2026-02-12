@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Services\LanguageService::trans('site_title', $lang ?? 'ja'))</title>
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
    <link rel="stylesheet" href="{{ asset('css/inline-styles.css') }}">
    @stack('styles')
</head>
<body>
    @if(!request()->routeIs('admin.*'))
        @include('layouts.header', ['lang' => $lang])
    @endif
    
    <main class="main-content">
        @if (session('translation_api_called'))
            <script>alert('テスト用: 翻訳APIが呼び出されました');</script>
        @endif
        @if (session('login_reward_message'))
            <div class="alert alert-success alert-margin">
                {{ session('login_reward_message') }}
            </div>
        @endif
        @if (session('friend_coin_received_message'))
            <div class="alert alert-success alert-margin">
                {{ session('friend_coin_received_message') }}
            </div>
        @endif
        @yield('content')
    </main>
    
    <!-- ルーム作成モーダル -->
    <div class="modal-overlay" id="createThreadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>{{ \App\Services\LanguageService::trans('create_thread_title', $lang) }}</h2>
                <button class="modal-close-btn" id="closeCreateThreadModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- 新規ルームフォーム -->
                <section class="post-form">
                    <form action="{{ route('threads.store') }}" method="POST" enctype="multipart/form-data">
                        <!-- CSRF保護 -->
                        @csrf
                        <div class="form-group">
                            <label for="user_name">{{ \App\Services\LanguageService::trans('thread_author', $lang) }}:</label>
                            <input type="text" id="user_name" name="user_name" value="{{ Auth::check() ? Auth::user()->username : old('user_name') }}" maxlength="20" required>
                            <small>20{{ \App\Services\LanguageService::trans('characters', $lang) }}以内</small>
                        </div>
                        <div class="form-group">
                            <label for="title">{{ \App\Services\LanguageService::trans('thread_title', $lang) }}:</label>
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="50" required>
                            <small>50{{ \App\Services\LanguageService::trans('characters', $lang) }}以内</small>
                        </div>
                        <div class="form-group">
                            <label for="body">{{ \App\Services\LanguageService::trans('thread_body', $lang) }}:</label>
                            <textarea id="body" name="body" rows="5" maxlength="1000" required>{{ old('body') }}</textarea>
                            <small>1000{{ \App\Services\LanguageService::trans('characters', $lang) }}以内</small>
                        </div>
                        @php
                            $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
                        @endphp
                        <div class="form-group">
                            <label for="tag">{{ \App\Services\LanguageService::trans('thread_tag', $lang) }}:</label>
                            <select id="tag" name="tag" required>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('生活・日常', $lang) }}">
                                    <option value="家事">{{ \App\Services\LanguageService::transTag('家事', $lang) }}</option>
                                    <option value="節約・便利術">{{ \App\Services\LanguageService::transTag('節約・便利術', $lang) }}</option>
                                    <option value="住まい・引越し">{{ \App\Services\LanguageService::transTag('住まい・引越し', $lang) }}</option>
                                    <option value="食事・レシピ">{{ \App\Services\LanguageService::transTag('食事・レシピ', $lang) }}</option>
                                    <option value="ショッピング">{{ \App\Services\LanguageService::transTag('ショッピング', $lang) }}</option>
                                    <option value="育児">{{ \App\Services\LanguageService::transTag('育児', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('健康・医療', $lang) }}">
                                    <option value="病気・症状">{{ \App\Services\LanguageService::transTag('病気・症状', $lang) }}</option>
                                    <option value="健康管理・ライフスタイル">{{ \App\Services\LanguageService::transTag('健康管理・ライフスタイル', $lang) }}</option>
                                    <option value="フィットネス・運動">{{ \App\Services\LanguageService::transTag('フィットネス・運動', $lang) }}</option>
                                    <option value="医療制度">{{ \App\Services\LanguageService::transTag('医療制度', $lang) }}</option>
                                    <option value="介護">{{ \App\Services\LanguageService::transTag('介護', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('仕事・キャリア', $lang) }}">
                                    <option value="就職・転職">{{ \App\Services\LanguageService::transTag('就職・転職', $lang) }}</option>
                                    <option value="職場の悩み">{{ \App\Services\LanguageService::transTag('職場の悩み', $lang) }}</option>
                                    <option value="起業・経営">{{ \App\Services\LanguageService::transTag('起業・経営', $lang) }}</option>
                                    <option value="フリーランス・副業">{{ \App\Services\LanguageService::transTag('フリーランス・副業', $lang) }}</option>
                                    <option value="ビジネスマナー">{{ \App\Services\LanguageService::transTag('ビジネスマナー', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('学び・教育', $lang) }}">
                                    <option value="学校・大学">{{ \App\Services\LanguageService::transTag('学校・大学', $lang) }}</option>
                                    <option value="資格・検定">{{ \App\Services\LanguageService::transTag('資格・検定', $lang) }}</option>
                                    <option value="語学学習・留学">{{ \App\Services\LanguageService::transTag('語学学習・留学', $lang) }}</option>
                                    <option value="自己啓発">{{ \App\Services\LanguageService::transTag('自己啓発', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('テクノロジー・デジタル', $lang) }}">
                                    <option value="スマートフォン・アプリ">{{ \App\Services\LanguageService::transTag('スマートフォン・アプリ', $lang) }}</option>
                                    <option value="パソコン・周辺機器">{{ \App\Services\LanguageService::transTag('パソコン・周辺機器', $lang) }}</option>
                                    <option value="家電・IoT">{{ \App\Services\LanguageService::transTag('家電・IoT', $lang) }}</option>
                                    <option value="ソフトウェア・プログラミング">{{ \App\Services\LanguageService::transTag('ソフトウェア・プログラミング', $lang) }}</option>
                                    <option value="AI・機械学習">{{ \App\Services\LanguageService::transTag('AI・機械学習', $lang) }}</option>
                                    <option value="インターネット・SNS">{{ \App\Services\LanguageService::transTag('インターネット・SNS', $lang) }}</option>
                                    <option value="ハードウェア・電子工作・ロボット">{{ \App\Services\LanguageService::transTag('ハードウェア・電子工作・ロボット', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('趣味・エンタメ', $lang) }}">
                                    <option value="音楽">{{ \App\Services\LanguageService::transTag('音楽', $lang) }}</option>
                                    <option value="映画・ドラマ">{{ \App\Services\LanguageService::transTag('映画・ドラマ', $lang) }}</option>
                                    <option value="アニメ・漫画">{{ \App\Services\LanguageService::transTag('アニメ・漫画', $lang) }}</option>
                                    <option value="ゲーム">{{ \App\Services\LanguageService::transTag('ゲーム', $lang) }}</option>
                                    <option value="スポーツ">{{ \App\Services\LanguageService::transTag('スポーツ', $lang) }}</option>
                                    <option value="アート・クラフト">{{ \App\Services\LanguageService::transTag('アート・クラフト', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('旅行・地域', $lang) }}">
                                    <option value="旅行・観光地情報">{{ \App\Services\LanguageService::transTag('旅行・観光地情報', $lang) }}</option>
                                    <option value="地域の話題">{{ \App\Services\LanguageService::transTag('地域の話題', $lang) }}</option>
                                    <option value="交通・移動手段">{{ \App\Services\LanguageService::transTag('交通・移動手段', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('恋愛・人間関係', $lang) }}">
                                    <option value="恋愛相談">{{ \App\Services\LanguageService::transTag('恋愛相談', $lang) }}</option>
                                    <option value="結婚・離婚">{{ \App\Services\LanguageService::transTag('結婚・離婚', $lang) }}</option>
                                    <option value="家族関係">{{ \App\Services\LanguageService::transTag('家族関係', $lang) }}</option>
                                    <option value="友人・人付き合い">{{ \App\Services\LanguageService::transTag('友人・人付き合い', $lang) }}</option>
                                    <option value="性・ジェンダー">{{ \App\Services\LanguageService::transTag('性・ジェンダー', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('お金・法律・制度', $lang) }}">
                                    <option value="貯金・投資">{{ \App\Services\LanguageService::transTag('貯金・投資', $lang) }}</option>
                                    <option value="税金・年金">{{ \App\Services\LanguageService::transTag('税金・年金', $lang) }}</option>
                                    <option value="保険">{{ \App\Services\LanguageService::transTag('保険', $lang) }}</option>
                                    <option value="法律相談">{{ \App\Services\LanguageService::transTag('法律相談', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('社会・政治・国際', $lang) }}">
                                    <option value="ニュース">{{ \App\Services\LanguageService::transTag('ニュース', $lang) }}</option>
                                    <option value="国際情勢">{{ \App\Services\LanguageService::transTag('国際情勢', $lang) }}</option>
                                    <option value="政治・政策">{{ \App\Services\LanguageService::transTag('政治・政策', $lang) }}</option>
                                    <option value="社会問題・人権">{{ \App\Services\LanguageService::transTag('社会問題・人権', $lang) }}</option>
                                    <option value="災害・緊急情報">{{ \App\Services\LanguageService::transTag('災害・緊急情報', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('文化・宗教・歴史', $lang) }}">
                                    <option value="伝統文化">{{ \App\Services\LanguageService::transTag('伝統文化', $lang) }}</option>
                                    <option value="宗教・信仰">{{ \App\Services\LanguageService::transTag('宗教・信仰', $lang) }}</option>
                                    <option value="歴史・考古学">{{ \App\Services\LanguageService::transTag('歴史・考古学', $lang) }}</option>
                                    <option value="哲学・思想">{{ \App\Services\LanguageService::transTag('哲学・思想', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('科学・自然・宇宙', $lang) }}">
                                    <option value="科学・テクノロジー">{{ \App\Services\LanguageService::transTag('科学・テクノロジー', $lang) }}</option>
                                    <option value="自然・エコロジー">{{ \App\Services\LanguageService::transTag('自然・エコロジー', $lang) }}</option>
                                    <option value="宇宙・天文">{{ \App\Services\LanguageService::transTag('宇宙・天文', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('ペット・動物', $lang) }}">
                                    <option value="犬・猫">{{ \App\Services\LanguageService::transTag('犬・猫', $lang) }}</option>
                                    <option value="小動物">{{ \App\Services\LanguageService::transTag('小動物', $lang) }}</option>
                                    <option value="鳥類">{{ \App\Services\LanguageService::transTag('鳥類', $lang) }}</option>
                                    <option value="魚類・水生生物">{{ \App\Services\LanguageService::transTag('魚類・水生生物', $lang) }}</option>
                                    <option value="爬虫類・両生類">{{ \App\Services\LanguageService::transTag('爬虫類・両生類', $lang) }}</option>
                                    <option value="昆虫">{{ \App\Services\LanguageService::transTag('昆虫', $lang) }}</option>
                                    <option value="畜産動物">{{ \App\Services\LanguageService::transTag('畜産動物', $lang) }}</option>
                                    <option value="飼い方・しつけ">{{ \App\Services\LanguageService::transTag('飼い方・しつけ', $lang) }}</option>
                                    <option value="ペットの健康・病気">{{ \App\Services\LanguageService::transTag('ペットの健康・病気', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('植物・ガーデニング', $lang) }}">
                                    <option value="観葉植物・園芸">{{ \App\Services\LanguageService::transTag('観葉植物・園芸', $lang) }}</option>
                                    <option value="野菜・果物の栽培">{{ \App\Services\LanguageService::transTag('野菜・果物の栽培', $lang) }}</option>
                                    <option value="多肉植物・珍奇植物">{{ \App\Services\LanguageService::transTag('多肉植物・珍奇植物', $lang) }}</option>
                                    <option value="植物の育て方・病害虫">{{ \App\Services\LanguageService::transTag('植物の育て方・病害虫', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('不思議・オカルト', $lang) }}">
                                    <option value="心霊・幽霊体験">{{ \App\Services\LanguageService::transTag('心霊・幽霊体験', $lang) }}</option>
                                    <option value="超常現象">{{ \App\Services\LanguageService::transTag('超常現象', $lang) }}</option>
                                    <option value="陰謀論・都市伝説">{{ \App\Services\LanguageService::transTag('陰謀論・都市伝説', $lang) }}</option>
                                    <option value="占い・スピリチュアル">{{ \App\Services\LanguageService::transTag('占い・スピリチュアル', $lang) }}</option>
                                    <option value="前世・輪廻・夢">{{ \App\Services\LanguageService::transTag('前世・輪廻・夢', $lang) }}</option>
                                </optgroup>
                                <optgroup label="{{ \App\Services\LanguageService::transTag('雑談・ユーモア', $lang) }}">
                                    <option value="雑談">{{ \App\Services\LanguageService::transTag('雑談', $lang) }}</option>
                                    <option value="ジョーク・小ネタ">{{ \App\Services\LanguageService::transTag('ジョーク・小ネタ', $lang) }}</option>
                                    <option value="体験談">{{ \App\Services\LanguageService::transTag('体験談', $lang) }}</option>
                                </optgroup>
                                @if($isAdult)
                                <optgroup label="{{ \App\Services\LanguageService::transTag('R18・アダルト', $lang) }}">
                                    <option value="成人向けメディア・コンテンツ・創作">{{ \App\Services\LanguageService::transTag('成人向けメディア・コンテンツ・創作', $lang) }}</option>
                                    <option value="性体験談・性的嗜好・フェティシズム">{{ \App\Services\LanguageService::transTag('性体験談・性的嗜好・フェティシズム', $lang) }}</option>
                                    <option value="アダルト業界・風俗・ナイトワーク">{{ \App\Services\LanguageService::transTag('アダルト業界・風俗・ナイトワーク', $lang) }}</option>
                                </optgroup>
                                @endif
                                <optgroup label="{{ \App\Services\LanguageService::transTag('Q&A・その他', $lang) }}">
                                    <option value="Q&A（なんでも質問）">{{ \App\Services\LanguageService::transTag('Q&A（なんでも質問）', $lang) }}</option>
                                    <option value="その他" selected>{{ \App\Services\LanguageService::transTag('その他', $lang) }}</option>
                                </optgroup>
                            </select>
                        </div>
                        @if($isAdult)
                        <div class="form-group">
                            <label class="label-flex">
                                {{ \App\Services\LanguageService::trans('r18_thread_checkbox', $lang) }}
                                <input type="checkbox" id="is_r18" name="is_r18" value="1" {{ old('is_r18') ? 'checked' : '' }}>
                            </label>
                            <small>{{ \App\Services\LanguageService::trans('r18_thread_checkbox_description', $lang) }}</small>
                        </div>
                        @endif
                        <div class="form-group">
                            <label for="image">{{ \App\Services\LanguageService::trans('thread_image', $lang) }} ({{ \App\Services\LanguageService::trans('optional', $lang) }}・{{ \App\Services\LanguageService::trans('image_aspect_ratio', $lang) }}):</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="image" name="image" accept="image/*" class="file-input-hidden">
                                <label for="image" class="file-input-label">
                                    <span class="file-input-button">{{ \App\Services\LanguageService::trans('select_image', $lang) }}</span>
                                    <span class="file-input-text" id="imageFileName">{{ \App\Services\LanguageService::trans('no_file_selected', $lang) }}</span>
                                </label>
                            </div>
                            <small>{{ \App\Services\LanguageService::trans('image_help', $lang) }}</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">{{ \App\Services\LanguageService::trans('create_thread', $lang) }}</button>
                            <button type="button" class="btn btn-secondary" id="cancelCreateThread">{{ \App\Services\LanguageService::trans('cancel', $lang) }}</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
    
    <!-- 通報モーダル -->
    <div class="modal-overlay" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>{{ \App\Services\LanguageService::trans('report_modal_title', $lang) }}</h2>
                <button class="modal-close-btn" id="closeReportModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="reportForm" action="{{ route('reports.store') }}" method="POST">
                    @csrf
                    <input type="hidden" id="report_thread_id" name="thread_id" value="">
                    <input type="hidden" id="report_response_id" name="response_id" value="">
                    <input type="hidden" id="report_reported_user_id" name="reported_user_id" value="">
                    
                    <div class="form-group">
                        <label for="report_reason">{{ \App\Services\LanguageService::trans('report_reason_label', $lang) }}:</label>
                        <select id="report_reason" name="reason" required>
                            <option value="">{{ \App\Services\LanguageService::trans('select_please', $lang) }}</option>
                            <option value="スパム・迷惑行為">{{ \App\Services\LanguageService::trans('report_reason_spam', $lang) }}</option>
                            <option value="攻撃的・不適切な内容">{{ \App\Services\LanguageService::trans('report_reason_offensive', $lang) }}</option>
                            <option value="不適切なリンク・外部誘導">{{ \App\Services\LanguageService::trans('report_reason_inappropriate_link', $lang) }}</option>
                            <option value="成人向け以外のコンテンツ規制違反">{{ \App\Services\LanguageService::trans('report_reason_content_violation', $lang) }}</option>
                            <option value="異なる思想に関しての意見の押し付け、妨害">{{ \App\Services\LanguageService::trans('report_reason_opinion_imposition', $lang) }}</option>
                            <option value="その他">{{ \App\Services\LanguageService::trans('other', $lang) }}</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="report_description">{{ \App\Services\LanguageService::trans('report_description_label', $lang) }}:</label>
                        <textarea id="report_description" name="description" rows="4" maxlength="300" placeholder="{{ \App\Services\LanguageService::trans('report_description_placeholder', $lang) }}"></textarea>
                        <small>{{ \App\Services\LanguageService::trans('report_description_max', $lang) }}</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ \App\Services\LanguageService::trans('report_submit_button', $lang) }}</button>
                        <button type="button" class="btn btn-secondary" id="cancelReport">{{ \App\Services\LanguageService::trans('report_cancel_button', $lang) }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    @include('layouts.scripts')
</body>
</html>

<!-- タグ一覧 -->
@php
    // ViewComposerから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<div class="tag-list">
    <div class="tag-category">
        <h4>1. {{ \App\Services\LanguageService::transTag('生活・日常', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '家事') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('家事', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '節約・便利術') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('節約・便利術', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '住まい・引越し') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('住まい・引越し', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '食事・レシピ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('食事・レシピ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ショッピング') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ショッピング', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '育児') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('育児', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>2. {{ \App\Services\LanguageService::transTag('健康・医療', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '病気・症状') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('病気・症状', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '健康管理・ライフスタイル') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('健康管理・ライフスタイル', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'フィットネス・運動') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('フィットネス・運動', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '医療制度') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('医療制度', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '介護') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('介護', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>3. {{ \App\Services\LanguageService::transTag('仕事・キャリア', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '就職・転職') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('就職・転職', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '職場の悩み') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('職場の悩み', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '起業・経営') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('起業・経営', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'フリーランス・副業') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('フリーランス・副業', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ビジネスマナー') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ビジネスマナー', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>4. {{ \App\Services\LanguageService::transTag('学び・教育', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '学校・大学') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('学校・大学', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '資格・検定') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('資格・検定', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '語学学習・留学') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('語学学習・留学', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '自己啓発') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('自己啓発', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>5. {{ \App\Services\LanguageService::transTag('テクノロジー・デジタル', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', 'スマートフォン・アプリ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('スマートフォン・アプリ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'パソコン・周辺機器') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('パソコン・周辺機器', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '家電・IoT') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('家電・IoT', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ソフトウェア・プログラミング') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ソフトウェア・プログラミング', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'AI・機械学習') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('AI・機械学習', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'インターネット・SNS') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('インターネット・SNS', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ハードウェア・電子工作・ロボット') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ハードウェア・電子工作・ロボット', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>6. {{ \App\Services\LanguageService::transTag('趣味・エンタメ', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '音楽') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('音楽', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '映画・ドラマ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('映画・ドラマ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'アニメ・漫画') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('アニメ・漫画', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ゲーム') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ゲーム', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'スポーツ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('スポーツ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'アート・クラフト') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('アート・クラフト', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>7. {{ \App\Services\LanguageService::transTag('旅行・地域', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '旅行・観光地情報') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('旅行・観光地情報', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '地域の話題') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('地域の話題', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '交通・移動手段') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('交通・移動手段', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>8. {{ \App\Services\LanguageService::transTag('恋愛・人間関係', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '恋愛相談') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('恋愛相談', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '結婚・離婚') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('結婚・離婚', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '家族関係') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('家族関係', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '友人・人付き合い') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('友人・人付き合い', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '性・ジェンダー') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('性・ジェンダー', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>9. {{ \App\Services\LanguageService::transTag('お金・法律・制度', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '貯金・投資') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('貯金・投資', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '税金・年金') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('税金・年金', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '保険') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('保険', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '法律相談') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('法律相談', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>10. {{ \App\Services\LanguageService::transTag('社会・政治・国際', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', 'ニュース') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ニュース', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '国際情勢') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('国際情勢', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '政治・政策') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('政治・政策', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '社会問題・人権') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('社会問題・人権', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '災害・緊急情報') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('災害・緊急情報', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>11. {{ \App\Services\LanguageService::transTag('文化・宗教・歴史', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '伝統文化') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('伝統文化', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '宗教・信仰') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('宗教・信仰', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '歴史・考古学') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('歴史・考古学', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '哲学・思想') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('哲学・思想', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>12. {{ \App\Services\LanguageService::transTag('科学・自然・宇宙', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '科学・テクノロジー') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('科学・テクノロジー', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '自然・エコロジー') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('自然・エコロジー', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '宇宙・天文') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('宇宙・天文', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>13. {{ \App\Services\LanguageService::transTag('ペット・動物', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '犬・猫') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('犬・猫', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '小動物') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('小動物', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '鳥類') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('鳥類', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '魚類・水生生物') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('魚類・水生生物', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '爬虫類・両生類') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('爬虫類・両生類', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '昆虫') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('昆虫', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '畜産動物') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('畜産動物', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '飼い方・しつけ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('飼い方・しつけ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ペットの健康・病気') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ペットの健康・病気', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>14. {{ \App\Services\LanguageService::transTag('植物・ガーデニング', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '観葉植物・園芸') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('観葉植物・園芸', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '野菜・果物の栽培') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('野菜・果物の栽培', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '多肉植物・珍奇植物') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('多肉植物・珍奇植物', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '植物の育て方・病害虫') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('植物の育て方・病害虫', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>15. {{ \App\Services\LanguageService::transTag('不思議・オカルト', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '心霊・幽霊体験') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('心霊・幽霊体験', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '超常現象') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('超常現象', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '陰謀論・都市伝説') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('陰謀論・都市伝説', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '占い・スピリチュアル') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('占い・スピリチュアル', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '前世・輪廻・夢') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('前世・輪廻・夢', $lang) }}</a></li>
        </ul>
    </div>
    
    <div class="tag-category">
        <h4>16. {{ \App\Services\LanguageService::transTag('雑談・ユーモア', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '雑談') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('雑談', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'ジョーク・小ネタ') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('ジョーク・小ネタ', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '体験談') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('体験談', $lang) }}</a></li>
        </ul>
    </div>
    
    @php
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : true;
    @endphp
    @if($isAdult)
    <div class="tag-category">
        <h4>17. {{ \App\Services\LanguageService::transTag('R18・アダルト', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', '成人向けメディア・コンテンツ・創作') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('成人向けメディア・コンテンツ・創作', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', '性体験談・性的嗜好・フェティシズム') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('性体験談・性的嗜好・フェティシズム', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'アダルト業界・風俗・ナイトワーク') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('アダルト業界・風俗・ナイトワーク', $lang) }}</a></li>
        </ul>
    </div>
    @endif
    
    <div class="tag-category">
        <h4>{{ $isAdult ? '18' : '17' }}. {{ \App\Services\LanguageService::transTag('Q&A・その他', $lang) }}</h4>
        <ul>
            <li><a href="{{ route('threads.tag', 'Q&A（なんでも質問）') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('Q&A（なんでも質問）', $lang) }}</a></li>
            <li><a href="{{ route('threads.tag', 'その他') }}" class="tag-link">{{ \App\Services\LanguageService::transTag('その他', $lang) }}</a></li>
        </ul>
    </div>
</div>

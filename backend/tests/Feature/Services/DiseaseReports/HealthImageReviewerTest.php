<?php

use App\Services\DiseaseReports\HealthImageReviewer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.openrouter.key' => 'test-key', 'services.openrouter.model' => 'qwen/test-model']);

    $this->reviewer = app(HealthImageReviewer::class);
    $this->categories = ['Wilting or drying', 'Yellowing leaves', 'Not sure'];
    $this->review = fn() => $this->reviewer->review('IMAGEBYTES', 'image/jpeg', 'The leaves are drooping', 'Crop', $this->categories);
    $this->reply = fn(array $content, int $status = 200) => Http::fake([
        'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($content)]]]], $status),
    ]);
    $this->good = ['photo_usable' => true, 'category' => 'Wilting or drying', 'confidence' => 0.82, 'urgent' => false, 'note' => 'Leaves look dry at the edges.'];
});

test('a good answer comes back as a review', function () {
    ($this->reply)($this->good);

    $review = ($this->review)();

    expect($review->photoUsable)->toBeTrue()
        ->and($review->category)->toBe('Wilting or drying')
        ->and($review->confidence)->toBe(0.82)
        ->and($review->urgent)->toBeFalse()
        ->and($review->note)->toBe('Leaves look dry at the edges.')
        ->and($review->model)->toBe('qwen/test-model');
});

test('the request carries the key, the model, the photo and the description and nothing personal', function () {
    ($this->reply)($this->good);

    ($this->review)();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $text = json_encode($body['messages'], JSON_UNESCAPED_SLASHES);

        return $request->hasHeader('Authorization', 'Bearer test-key')
            && $body['model'] === 'qwen/test-model'
            && str_contains($text, 'data:image/jpeg;base64,' . base64_encode('IMAGEBYTES'))
            && str_contains($text, 'The leaves are drooping')
            && $body['provider']['data_collection'] === 'deny'
            && $body['response_format']['json_schema']['schema']['properties']['category']['enum'] === ['Wilting or drying', 'Yellowing leaves', 'Not sure'];
    });
});

test('a category that is not on the list is refused', function () {
    ($this->reply)([...$this->good, 'category' => 'Made up disease']);

    expect(($this->review)())->toBeNull();
});

test('a confidence outside 0 to 1 is refused', function () {
    ($this->reply)([...$this->good, 'confidence' => 82]);

    expect(($this->review)())->toBeNull();
});

test('an unusable photo is reported as such without needing a category', function () {
    ($this->reply)(['photo_usable' => false, 'category' => 'Not sure', 'confidence' => 0.0, 'urgent' => false, 'note' => '']);

    $review = ($this->review)();

    expect($review->photoUsable)->toBeFalse();
});

test('a failed call, a bad body or a missing key gives no review and never throws', function () {
    Http::fake(['openrouter.ai/*' => Http::response('boom', 500)]);
    expect(($this->review)())->toBeNull();

    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'not json']]]])]);
    expect(($this->review)())->toBeNull();

    Http::fake(['openrouter.ai/*' => Http::response(['nothing' => true])]);
    expect(($this->review)())->toBeNull();

    config(['services.openrouter.key' => null]);
    Http::fake();
    expect(($this->review)())->toBeNull();
    Http::assertNothingSent();
});

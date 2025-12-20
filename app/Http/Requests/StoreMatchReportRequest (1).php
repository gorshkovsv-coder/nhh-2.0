<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMatchReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

	public function rules(): array {
		return [
			'score_home' => ['required','integer','min:0','max:99'],
			'score_away' => ['required','integer','min:0','max:99','different:score_home'],
			'ot'         => ['nullable','boolean'],
			'so'         => ['nullable','boolean'],
			'comment'    => ['nullable','string','max:2000'],

			// ⬇⬇⬇ ВЛОЖЕНИЯ ТЕПЕРЬ ОБЯЗАТЕЛЬНЫ ⬇⬇⬇
			'attachments'   => ['required','array','min:1'],
			'attachments.*' => ['file', 'image', 'max:51200'],
		];
	}

	public function messages(): array
{
    return [
        'attachments.required' => 'Пожалуйста, приложите хотя бы один скриншот матча.',
        'attachments.array'    => 'Скриншоты должны быть переданы как массив файлов.',
        'attachments.min'      => 'Пожалуйста, приложите хотя бы один скриншот матча.',

        'attachments.*.image'  => 'Каждое вложение должно быть изображением (JPG/PNG).',
        'attachments.*.max'    => 'Размер каждого скриншота не должен превышать 50 МБ.',
    ];
}


    public function withValidator($validator) {
        $validator->after(function($v){
            $ot = (bool)$this->input('ot', false);
            $so = (bool)$this->input('so', false);
            if ($ot && $so) {
                $v->errors()->add('ot', 'Нельзя одновременно OT и SO.');
            }
        });
    }
}

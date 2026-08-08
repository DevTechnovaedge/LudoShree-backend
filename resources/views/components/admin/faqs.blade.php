@if(old('faq'))
    @foreach(old('faq') as $faq)
        <!-- Static Faq Card -->
        <div class="card p-4 faq clone-template" data-clone-template-id="{{ $loop->iteration }}">
            <div class="row item-row">
                <!-- Question -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="question">Question</label>
                        <input type="text" class="form-control" placeholder="Enter question" name="faq[{{ $loop->iteration }}][question]" id="question" value="{{ $faq['question'] ?? '' }}">
                        @error("faq.$loop->iteration.question") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Question -->
                
                <!-- Answer -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="answer">Answer</label>
                        <textarea class="form-control" placeholder="Enter answer" name="faq[{{ $loop->iteration }}][answer]" id="answer"> {{ $faq['answer'] ?? '' }} </textarea>
                        @error("faq.$loop->iteration.answer") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Answer -->
            </div>
        </div>
        <!-- End Static Faq Card -->
    @endforeach
@else
    @forelse($items as $item)
        
        <!-- Static Faq Card -->
        <div class="card p-4 faq clone-template" data-clone-template-id="{{ $loop->iteration }}">
        
            @if(!$loop->first)
                <span class="remove-clone-template-row fa fa-trash" onclick="removeCloneTemplateRow(this)"></span>
            @endif

            <div class="row item-row">
                <!-- Question -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="question">Question</label>
                        <input type="text" class="form-control" placeholder="Enter question" name="faq[{{ $loop->iteration }}][question]" id="question" value="{{ $item->question ?? '' }}">
                        @error("faq.$loop->iteration.question") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Question -->

                <!-- Answer -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="answer">Answer</label>
                        <textarea class="form-control" placeholder="Enter answer" name="faq[{{ $loop->iteration }}][answer]" id="answer">{{ $item->answer ?? '' }}</textarea>
                        @error("faq.$loop->iteration.answer") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Answer -->
            </div>
        </div>
        <!-- End Static Faq Card -->

    @empty

        <!-- Static Faq Card -->
        <div class="card p-4 faq clone-template" data-clone-template-id="1">
            <div class="row item-row">
                <!-- Question -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="question">Question</label>
                        <input type="text" class="form-control" placeholder="Enter question" name="faq[1][question]" id="question">
                        @error("faq.1.question") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Question -->
                
                <!-- Answer -->
                <div class="col-md-12">
                    <div class="form-group">
                        <label for="answer">Answer</label>
                        <textarea class="form-control" placeholder="Enter answer" name="faq[1][answer]" id="answer"></textarea>
                        @error("faq.1.answer") <span class="text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <!-- End Answer -->
            </div>
        </div>
        <!-- End Static Faq Card -->

    @endforelse
@endif
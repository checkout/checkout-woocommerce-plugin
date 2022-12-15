document.querySelector('#ideal-bank-info input').addEventListener('input', function(e) {
    var input = e.target;
    var list = input.getAttribute('list');
    var options = document.querySelectorAll('#' + list + ' option');
	var hiddenInputName = input.getAttribute('name').slice(0, -6);
    var hiddenInput = document.getElementById(hiddenInputName);
    var inputValue = input.value;

    hiddenInput.value = inputValue;

    for(var i = 0; i < options.length; i++) {
        var option = options[i];

        if(option.value === inputValue) {
            hiddenInput.value = option.getAttribute('data-value');
            break;
        }
    }
});

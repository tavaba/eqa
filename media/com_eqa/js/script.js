function confirmDelete(msg, url) {
    if (confirm(msg)) {
        window.location.href = url;
    }
}
$(document).ready(function() {
    $('.select2-basic').select2(
        {
            "width": "100%"
        }
    );
});

/**
 * Tính tổng giá trị ở các field có 'id' được thỏa mãn pattern nhất định
 * rồi ghi vào field có id được chỉ định (sumFieldid)
 * Được sử dụng ở form chia phòng thi cho thí sinh
 * @param operandFieldIdPattern
 * @param sumFieldId
 */
function updateSum(operandFieldIdPattern, sumFieldId)
{
    let sumField = document.getElementById(sumFieldId);

    // Get all child elements with an id attribute
    let allElements = document.querySelectorAll('[id]');

    // Filter the array with the pattern
    let regex = new RegExp(operandFieldIdPattern);
    let operandFields =   Array.from(allElements).filter(el => regex.test(el.id));

    //Calculate the sum
    let sum=0;
    for(let i=0; i<operandFields.length; i++){
        sum += Number(operandFields[i].value);
    }
    sumField.value = sum;
}

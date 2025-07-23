/**
 * Script này được sử dụng trong layout 'fixpam' của view 'fixer'.
 * Nó thực hiện việc lấy danh sách sinh viên dựa trên lớp đã chọn và
 * hiển thị chúng trong trường lựa chọn.
 *
 */
document.addEventListener('DOMContentLoaded', function() {
    alert('The fixpam script is loaded!');
    const classField = document.getElementById('jform_class_id');
    const studentField = document.getElementById('jform_learner_id');


    console.log(classField);
    console.log(studentField);

    if (classField && studentField) {
        classField.addEventListener('change', function() {
            const classId = this.value;

            if (!classId) {
                // Clear student field if no class selected
                studentField.innerHTML =  '<option value="">-Chọn sinh viên-</option>';
                return;
            }

            // Make AJAX request
            Joomla.request({
                url: 'index.php?option=com_eqa&task=students.getStudentsByClass&format=json',
                method: 'POST',
                data: {
                    class_id: classId,
                    [Joomla.getOptions('csrf.token')]: 1
                },
                onSuccess: function(response) {
                    try {
                        const data = JSON.parse(response);

                        if (data.success === false) {
                            console.error(data.message);
                            return;
                        }

                        // Clear existing options
                        studentField.innerHTML = '<option value="">-Chọn sinh viên-</option>';

                        // Add new options
                        data.data.forEach(function(student) {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = student.name;
                            studentField.appendChild(option);
                        });
                    } catch (e) {
                        console.error('Error parsing response', e);
                    }
                },
                onError: function(xhr) {
                    console.error('Request failed', xhr.statusText);
                }
            });
        });
    }
});
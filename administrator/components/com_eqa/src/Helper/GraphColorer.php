<?php
/**
 * @package     Kma\Component\Eqa\Administrator\Helper
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Component\Eqa\Administrator\Helper;


class GraphColorer
{
	private $M; // Maximum exams per subset
	private $examStudents; // Map: examId -> array of studentIds
	private $studentExams; // Map: studentId -> array of examIds
	private $examConflicts; // Map: examId -> set of conflicting examIds

	public function __construct($M, $examData)
	{
		$this->M             = $M;
		$this->examStudents  = [];
		$this->studentExams  = [];
		$this->examConflicts = [];

		// Build data structures
		$this->buildDataStructures($examData);
		$this->buildConflictGraph();
	}

	private function buildDataStructures($examData)
	{
		foreach ($examData as $pair)
		{
			$examId    = $pair[0];
			$studentId = $pair[1];

			// Build examStudents mapping
			if (!isset($this->examStudents[$examId]))
			{
				$this->examStudents[$examId] = [];
			}
			$this->examStudents[$examId][] = $studentId;

			// Build studentExams mapping
			if (!isset($this->studentExams[$studentId]))
			{
				$this->studentExams[$studentId] = [];
			}
			$this->studentExams[$studentId][] = $examId;
		}

		// Remove duplicate students for each exam
		foreach ($this->examStudents as $examId => $students)
		{
			$this->examStudents[$examId] = array_unique($students);
		}
	}

	private function buildConflictGraph()
	{
		foreach ($this->examStudents as $examId => $students)
		{
			$this->examConflicts[$examId] = [];

			// For each student taking this exam
			foreach ($students as $studentId)
			{
				// Find all other exams this student takes
				foreach ($this->studentExams[$studentId] as $otherExamId)
				{
					if ($otherExamId != $examId)
					{
						$this->examConflicts[$examId][$otherExamId] = true;
					}
				}
			}
		}
	}

	public function schedule()
	{
		$exams    = array_keys($this->examStudents);
		$subsets  = [];
		$assigned = [];

		// Sort exams by degree (number of conflicts) in descending order
		usort($exams, function ($a, $b) {
			return count($this->examConflicts[$b]) - count($this->examConflicts[$a]);
		});

		// Initial placement using greedy approach
		foreach ($exams as $examId)
		{
			if (isset($assigned[$examId]))
			{
				continue;
			}

			$bestSubsetIndex = $this->findBestSubset($examId, $subsets);

			if ($bestSubsetIndex !== -1)
			{
				$subsets[$bestSubsetIndex][] = $examId;
			}
			else
			{
				$subsets[] = [$examId];
			}
			$assigned[$examId] = true;
		}

		// Balance the subsets to satisfy 2*MIN > MAX condition
		$subsets = $this->balanceSubsets($subsets);

		return $subsets;
	}

	private function findBestSubset($examId, $subsets)
	{
		$candidates = [];

		foreach ($subsets as $index => $subset)
		{
			if ($this->canAddToSubset($examId, $subset))
			{
				$studentCount = $this->getSubsetStudentCount($subset);
				$candidates[] = ['index' => $index, 'students' => $studentCount];
			}
		}

		if (empty($candidates))
		{
			return -1;
		}

		// Choose the subset with the fewest students to promote balance
		usort($candidates, function ($a, $b) {
			return $a['students'] - $b['students'];
		});

		return $candidates[0]['index'];
	}

	private function canAddToSubset($examId, $subset)
	{
		// Check size constraint
		if (count($subset) >= $this->M)
		{
			return false;
		}

		// Check conflict constraint
		foreach ($subset as $existingExamId)
		{
			if (isset($this->examConflicts[$examId][$existingExamId]))
			{
				return false;
			}
		}

		return true;
	}

	private function getSubsetStudentCount($subset)
	{
		$students = [];
		foreach ($subset as $examId)
		{
			foreach ($this->examStudents[$examId] as $studentId)
			{
				$students[$studentId] = true;
			}
		}

		return count($students);
	}

	private function balanceSubsets($subsets)
	{
		$maxIterations = 100; // Prevent infinite loops
		$iteration     = 0;

		while ($iteration < $maxIterations)
		{
			$studentCounts = [];
			foreach ($subsets as $index => $subset)
			{
				$studentCounts[$index] = $this->getSubsetStudentCount($subset);
			}

			if (empty($studentCounts)) break;

			$minCount = min($studentCounts);
			$maxCount = max($studentCounts);

			// Check if balance condition is satisfied
			if (2 * $minCount > $maxCount)
			{
				break; // Condition satisfied
			}

			// Find subset with minimum students and subset with maximum students
			$minIndex = array_search($minCount, $studentCounts);
			$maxIndex = array_search($maxCount, $studentCounts);

			// Try to move an exam from max subset to min subset
			$moved = $this->moveExamBetweenSubsets($subsets, $maxIndex, $minIndex);

			if (!$moved)
			{
				// Try to redistribute exams more generally
				$redistributed = $this->redistributeExams($subsets, $studentCounts);
				if (!$redistributed)
				{
					break; // Cannot improve further
				}
			}

			$iteration++;
		}

		return $subsets;
	}

	private function moveExamBetweenSubsets(&$subsets, $fromIndex, $toIndex)
	{
		foreach ($subsets[$fromIndex] as $examIndex => $examId)
		{
			// Check if we can move this exam to the target subset
			if ($this->canAddToSubset($examId, $subsets[$toIndex]))
			{
				// Move the exam
				$subsets[$toIndex][] = $examId;
				array_splice($subsets[$fromIndex], $examIndex, 1);

				return true;
			}
		}

		return false;
	}

	private function redistributeExams(&$subsets, $studentCounts)
	{
		// Sort subsets by student count
		$sortedIndices = array_keys($studentCounts);
		usort($sortedIndices, function ($a, $b) use ($studentCounts) {
			return $studentCounts[$a] - $studentCounts[$b];
		});

		// Try to move exams from fuller subsets to emptier ones
		for ($i = count($sortedIndices) - 1; $i > 0; $i--)
		{
			$fromIndex = $sortedIndices[$i];

			for ($j = 0; $j < $i; $j++)
			{
				$toIndex = $sortedIndices[$j];

				if ($this->moveExamBetweenSubsets($subsets, $fromIndex, $toIndex))
				{
					return true;
				}
			}
		}

		return false;
	}

	public function printSolution($subsets)
	{
		echo "Number of subsets: " . count($subsets) . "\n";

		$studentCounts = [];
		foreach ($subsets as $index => $subset)
		{
			$studentCounts[$index] = $this->getSubsetStudentCount($subset);
		}

		if (!empty($studentCounts))
		{
			$minStudents = min($studentCounts);
			$maxStudents = max($studentCounts);
			echo "Student distribution - MIN: $minStudents, MAX: $maxStudents\n";
			echo "Balance condition (2*MIN > MAX): " . (2 * $minStudents > $maxStudents ? "SATISFIED" : "NOT SATISFIED") . "\n";
			echo "Balance ratio: " . ($maxStudents > 0 ? round($minStudents / $maxStudents, 2) : "N/A") . "\n\n";
		}

		foreach ($subsets as $index => $subset)
		{
			$studentCount = $studentCounts[$index];
			echo "Subset " . ($index + 1) . " ($studentCount students): [" . implode(", ", $subset) . "]\n";

			// Show students in this subset
			$subsetStudents = [];
			foreach ($subset as $examId)
			{
				foreach ($this->examStudents[$examId] as $studentId)
				{
					$subsetStudents[$studentId] = true;
				}
			}
			echo "  Students: " . implode(", ", array_keys($subsetStudents)) . "\n";

			// Show exam-student details
			$examDetails = [];
			foreach ($subset as $examId)
			{
				$examDetails[] = "Exam $examId (" . implode(", ", $this->examStudents[$examId]) . ")";
			}
			echo "  Details: " . implode("; ", $examDetails) . "\n\n";
		}
	}

	public function validateSolution($subsets)
	{
		$valid = true;

		foreach ($subsets as $index => $subset)
		{
			// Check size constraint
			if (count($subset) > $this->M)
			{
				echo "ERROR: Subset " . ($index + 1) . " exceeds size limit ($this->M)\n";
				$valid = false;
			}

			// Check student conflict constraint
			$studentsInSubset = [];
			foreach ($subset as $examId)
			{
				foreach ($this->examStudents[$examId] as $studentId)
				{
					if (isset($studentsInSubset[$studentId]))
					{
						echo "ERROR: Student $studentId appears in multiple exams in subset " . ($index + 1) . "\n";
						$valid = false;
					}
					$studentsInSubset[$studentId] = true;
				}
			}
		}

		// Check balance condition
		$studentCounts = [];
		foreach ($subsets as $index => $subset)
		{
			$studentCounts[$index] = $this->getSubsetStudentCount($subset);
		}

		if (!empty($studentCounts))
		{
			$minStudents = min($studentCounts);
			$maxStudents = max($studentCounts);

			if (2 * $minStudents <= $maxStudents)
			{
				echo "ERROR: Balance condition not satisfied (2*MIN > MAX): 2*$minStudents = " . (2 * $minStudents) . " <= $maxStudents\n";
				$valid = false;
			}
		}

		if ($valid)
		{
			echo "Solution is valid and balanced!\n";
		}

		return $valid;
	}
}

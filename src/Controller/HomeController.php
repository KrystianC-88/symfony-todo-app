<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JSONResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use App\Entity\Task;
use DateTime;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {

        $tasks_db = $entityManager->getRepository(Task::class)->findAll();

        $tasks = [];
        foreach ($tasks_db as $task){
            $tasks[] =[
                'name' => $task->getName(),
                'description' => $task->getDescription(),
                'isDone' => $task->isIsDone(),
                'created' => $task->getCreateDate(),
                'done' => $task->getDoneDate(),
            ];
        }
        

        return $this->render('home/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/delete', name: 'todo_delete')]
    public function delete(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): JSONResponse
    {
        $id = $request->request->get('id');
        if(!$id) return $this->json(["result"=> false, "message"=>"ID parameter is missing in the request"]);

        $task = $entityManager->getRepository(Task::class)->find($id);
        if(!$task) return $this->json(["result" => false,"message" => "Task with ID {$id} successfully deleted"]);
        
        try{
            $entityManager->remove($task);
            $entityManager->flush();
            
            $result = true;

            $taskJson = $serializer->serialize($task, 'json');
        }
        catch (\Exception $e){
            $result = false;
        }
        


        return $this->json([
            "result" => $result,
            "task" => ($taskJson) ?: null,
        ]);

    }

    private function validateTask($task_name, $description, $validator){
        
        // Validate the task name
        $nameViolations = $validator->validate($task_name, [
            new Assert\NotBlank(['message' => 'Task name should not be blank.']),
            new Assert\Length(['max' => 255, 'maxMessage' => 'Task name should not exceed 255 characters.']),
        ]);
        
        foreach ($nameViolations as $violation) {
            return $violation->getMessage();
        }

        // Validate the description
        $descriptionViolations = $validator->validate($description, [
            new Assert\Length(['max' => 255, 'maxMessage' => 'Description should not exceed 255 characters.']),
        ]);

        foreach ($descriptionViolations as $violation) {
            return $violation->getMessage();
        }

        return null; // no validation errors
    }

    #[Route('/create', name: 'todo_create')]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, SerializerInterface $serializer): JSONResponse
    {
        $task_name = $request->request->get('task');
        $description = $request->request->get('description');
        $currentDate = new DateTime();

        $errors = $this->validateTask($task_name, $description, $validator);
        if(count($errors) > 0) return $this->json(['result' => false,'errors' => $errors]);
    
        $task = new Task();
        $task->setName($task_name);
        $task->setCreateDate($currentDate);

        $entityManager->persist($task);
        $entityManager->flush();

        $result = false;
        
        $result = $task->getId() !== null;
        $taskJson = $result ? $serializer->serialize($task, 'json') : null;
        
        return $this->json([
            'result' => $result,
            'task' => $taskJson
        ]);
    }


}

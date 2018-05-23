<?php

namespace AppBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/books/{bookId}/sections/{sectionId}")
     * @Method({"GET"})
     *
     * @ApiDoc(
     *      resource=true,
     *      description="Return xml content of a section.",
     *      requirements={
     *          {
     *              "name" = "bookId",
     *              "dataType" = "string",
     *              "description" = "Book ID"
     *          },
     *          {
     *              "name" = "sectionId",
     *              "dataType" = "string",
     *              "description" = "Section ID"
     *          }
     *      },
     *      section="Section",
     *      views = { "default", "athena" }
     * )
     *
     * @return Response
     */
    public function getSectionAction($bookId, $sectionId)
    {
        return $this->get('basex_http')->getSection($bookId, $sectionId, true);
    }
}
